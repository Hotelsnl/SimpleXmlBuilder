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
            if (!is_scalar($name)) {
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

            $name = trim((string) $name);

            $this->addAttribute($name, $value);
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
            $nodeValue = (string)$nodeValue;
        } catch (\Exception $e) {
            syslog(LOG_WARNING, $e->getMessage() . PHP_EOL . $e->getTraceAsString());
            return false;
        }

        $node = dom_import_simplexml($this);
        $no = $node->ownerDocument;
        $node->appendChild($no->createCDATASection($nodeValue));

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

                static::createXML($values, $xmlDocument);
            } else {
                if (is_array($values)) {
                    // Check if we have a numeric array. This means we need
                    // to add elements to the same node.
                    if ($values === array_values($values)) {
                        foreach ($values as $listing) {
                            static::createXML(
                                array($element => $listing),
                                $xmlDocument
                            );
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
                } elseif (is_scalar($values)) {
                    if (strpbrk($values, static::$dangerousCharacters)) {
                        /** @var SimpleXmlBuilder $child */
                        $child = $xmlDocument->addChild($element, null, $namespace);

                        if ($attributes) {
                            $child->addAttributes($attributes);
                        }

                        $child->addCData($values);
                    } else {
                        $child = $xmlDocument->addChild(
                            $element,
                            htmlspecialchars($values, ENT_QUOTES),
                            $namespace
                        );

                        if ($attributes) {
                            $child->addAttributes($attributes);
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
