<?php
/**
 * SimpleXmlBuilder an extended SimpleXMLElement.
 *
 * This object extends the possibilities of SimpleXMLElement to create an XML
 * document from a PHP array.
 * An extra function has been added for creating CDATA elements for escaping
 * texts that might contain characters that could render the XML invalid.
 *
 * @package HotelsNL
 * @subpackage SimpleXmlBuilder
 */

namespace HotelsNL\SimpleXmlBuilder;

use \SimpleXMLElement;

/**
 * Class for creating XML from an array.
 */
class SimpleXmlBuilder extends SimpleXMLElement
{
    /**
     * A list of characters that need escaping in XML.
     *
     * @var string $dangerousCharacters
     */
    private static $dangerousCharacters = '\'"><&';

    /**
     * Wrapper for addAttribute to allow for an array of attributes.
     *
     * @param array $attributes
     * @return void
     */
    public function addAttributes(array $attributes)
    {
        foreach ($attributes as $name => $value) {
            if (!is_string($name)) {
                syslog(
                    LOG_WARNING,
                    'Attribute name is not a string: ' . var_export($name, true)
                );

                // Skipping invalid attribute.
                continue;
            }

            if (!is_scalar($value)) {
                syslog(
                    LOG_WARNING,
                    'Attribute value is not a string: ' . var_export($value, true)
                );

                // Skipping invalid attribute.
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? 'TRUE' : 'FALSE';
            } else {
                $value = trim((string) $value);
            }

            $this->addAttribute(trim($name), $value);
        }
    }

    /**
     * Add a CDATA element around a node value.
     *
     * @param string $nodeValue
     * @return boolean false on error, true on success.
     */
    public function addCData($nodeValue)
    {
        try {
            $nodeValue = (string) $nodeValue;
        } catch (\Exception $e) {
            syslog(LOG_WARNING, $e->getMessage() . PHP_EOL . $e->getTraceAsString());
            return false;
        }

        /** @var \DOMElement $node */
        $node = dom_import_simplexml($this);
        $no = $node->ownerDocument;
        $node->appendChild($no->createCDATASection($nodeValue));

        return true;
    }

    /**
     * Append a value to a child.
     *
     * @param string|\DOMNode $nodeValue
     * @return boolean false on error, true on success.
     */
    public function appendChild($nodeValue)
    {
        if (!is_string($nodeValue) && !$nodeValue instanceof \DOMNode) {
            syslog(
                LOG_WARNING,
                'Not a valid nodevalue for appendChild: '
                . var_export($nodeValue)
            );

            return false;
        }

        /** @var \DOMElement $node */
        $node = dom_import_simplexml($this);

        // Create a DOMNode from the string.
        if (is_string($nodeValue)) {
            $no = $node->ownerDocument;
            $nodeValue = $no->createTextNode($nodeValue);
        }

        $node->appendChild($nodeValue);

        return true;
    }

    /**
     * Return a well-formed XML string based on SimpleXML element.
     *
     * @param string $filename Optional filename
     * @param boolean $longOutput Set to true if you want a more readable output.
     * @return string|boolean
     *  Returns false on error
     *  If a filename is given it will return true if writing was successful
     *  false otherwise.
     *  Returns the XML as string if no filename was given.
     */
    public function asXML($filename = null, $longOutput = false)
    {
        if (!is_bool($longOutput)) {
            syslog(LOG_WARNING, 'Not a valid long option given.');
            return false;
        }

        $dom = null;
        $xml = parent::asXML();

        if (empty($xml)) {
            syslog(LOG_WARNING, 'Empty XML document.');
            return false;
        }

        /** @var \DOMElement $node */
        $node = dom_import_simplexml($this);
        $dom = $node->ownerDocument;

        if ($longOutput) {
            $dom->formatOutput = true;
            $dom->preserveWhiteSpace = false;
        }

        if (!empty($filename)) {
            return $dom->save($filename) !== false;
        }

        return $dom->saveXML();
    }

    /**
     * Creates an xml document from an array.
     * Array keys @attributes can have associative arrays which will be converted
     * to attributes for the parent.
     * Array keys @namespace to define a namespace for a node.
     *
     * @param array $document
     * @param null|SimpleXmlBuilder $xmlDocument a SimpleXmlBuilder document to
     *     append to
     * @return SimpleXmlBuilder
     */
    public static function createXML(
        array $document = array(),
        SimpleXmlBuilder &$xmlDocument = null
    ) {
        foreach ($document as $element => $values) {
            $namespace = null;
            $attributes = '';

            if (!empty($values['@namespace'])) {
                $namespace = (string) $values['@namespace'];
                unset($values['@namespace']);
            }

            if (!empty($values['@attributes'])) {
                if (is_string($values['@attributes'])) {
                    list($key, $value) = explode('=', $values['@attributes']);
                    if (!empty($key) && !empty($value)) {
                        $attributes = array($key, $value);
                    }
                } elseif (is_array($values['@attributes'])) {
                    $attributes = $values['@attributes'];
                } else {
                    syslog(
                        LOG_WARNING,
                        '@attributes format not valid: ' . $values['@attributes']
                    );
                }

                unset($values['@attributes']);
            }

            // If values is empty, make sure an empty element is created.
            if (empty($values)) {
                $values = null;
            }

            // If there is only one value in the array and it is scalar,
            // make this the new value.
            if (is_array($values)
                && $values === array_values($values) // Numeric array check
                && count($values) === 1
                && is_scalar(current($values))
            ) {
                $values = current($values);
            }

            if (!isset($xmlDocument)) {
                $xmlDocument = new static(
                    "<?xml version=\"1.0\" encoding=\"UTF-8\"?><{$element}/>",
                    0,
                    false,
                    $namespace
                );

                if ($attributes) {
                    $xmlDocument->addAttributes($attributes);
                }

                if (!empty($values)) {
                    static::createXML($values, $xmlDocument);
                }
            } else {
                if (is_array($values)) {
                    // Check if we have a numeric array. This means we need
                    // to add elements to the same node.
                    if ($values === array_values($values)) {
                        foreach ($values as $listing) {
                            // Make sure attributes and namespace get propagated
                            // to the concurrent elements.
                            $listing = array(
                                $element => $listing
                            );

                            if (!empty($attributes)) {
                                $listing['@attributes'] = $attributes;
                            }

                            if (!empty($namespace)) {
                                $listing['@namespace'] = $namespace;
                            }

                            static::createXML($listing, $xmlDocument);
                        }
                    } else {
                        // Continue in the next node.
                        /** @var SimpleXmlBuilder $child */
                        $child = $xmlDocument->addChild($element, null, $namespace);

                        if ($attributes) {
                            $child->addAttributes($attributes);
                        }

                        static::createXML($values, $child);
                    };
                } elseif (is_scalar($values) || $values === null) {
                    /** @var SimpleXmlBuilder $child */
                    $child = $xmlDocument->addChild($element, null, $namespace);

                    if ($attributes) {
                        $child->addAttributes($attributes);
                    }

                    if (!empty($values)) {
                        // Replace carriage returns with simple newline.
                        $values = preg_replace('/\r\n?/', "\n", $values);

                        if (strpbrk($values, static::$dangerousCharacters) !== false) {
                            $child->addCData($values);
                        } else {
                            $child->appendChild($values);
                        }
                    }
                }
            }
        }

        if (!isset($xmlDocument)) {
            $xmlDocument = new static("<?xml version=\"1.0\" encoding=\"UTF-8\"?>");
        }

        return $xmlDocument;
    }

    /**
     * Get the parent node of the current working node.
     *
     * @return SimpleXMLElement
     */
    public function getParentNode()
    {
        return current($this->xpath('parent::*'));
    }
}
