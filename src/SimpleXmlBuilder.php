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
     * Creates an xml document from an array.
     * Array keys @attributes can have associative arrays which will be converted
     * to attributes for the parent.
     * Array keys @namespace to define a namespace for a node.
     *
     * @param array $document
     * @param null|SimpleXmlBuilder $xmlDocument a SimpleXmlBuilder document to
     *     append to.
     * @return null|SimpleXmlBuilder Will return null if the $document was empty.
     */
    public static function createXML(array $document, &$xmlDocument = null)
    {
        foreach ($document as $element => $values) {
            $namespace = null;
            $attributes = '';

            if (!empty($values['@namespace'])) {
                $namespace = (string) $values['@namespace'];
                unset($values['@namespace']);
            }

            if (!empty($values['@attributes'])) {
                array_walk(
                    $values['@attributes'],
                    function ($value, $key) use (&$attributes) {
                        $attributes .= "{$key}=\"{$value}\" ";
                    }
                );
                $attributes = trim($attributes);
                unset($values['@attributes']);
            }

            if (!isset($xmlDocument)) {
                $xmlDocument = new static(
                    "<?xml version=\"1.0\" encoding=\"UTF-8\"?><{$element} {$attributes}/>",
                    0,
                    false,
                    $namespace
                );
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
                        /** @var SimpleXmlBuilder $child */
                        // Continue in the next node.
                        $child = $xmlDocument->addChild($element, null, $namespace);
                        static::createXML($values, $child);
                    };
                } elseif (is_scalar($values)) {
                    if (strpbrk($values, static::$dangerousCharacters)) {
                        /** @var SimpleXmlBuilder $child */
                        $child = $xmlDocument->addChild($element, null, $namespace);
                        $child->addCData($values);
                    } else {
                        $xmlDocument->addChild(
                            $element,
                            htmlentities($values, ENT_QUOTES),
                            $namespace
                        );
                    }

                }
            }
        }

        // Returns null if $document was empty.
        return $xmlDocument;
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
            syslog(E_WARNING, $e->getMessage() . PHP_EOL . $e->getTraceAsString());
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
     * @param string $filename Optional filename.
     * @param boolean $longOutput .
     * @return string|void|false returns false on error,
     *  void if a filename is given,
     *  string if no filename was given.
     */
    public function asXML($filename = null, $longOutput = false)
    {
        if (!is_bool($longOutput)) {
            syslog(E_WARNING, 'Not a valid long option given.');
            return false;
        }

        $dom = null;
        $xml = parent::asXML();

        if (empty($xml)) {
            syslog(E_WARNING, 'Empty XML document.');
            return false;
        }

        $dom = new \DOMDocument("1.0");

        if ($longOutput) {
            $dom->formatOutput = true;
            $dom->preserveWhiteSpace = false;
        }

        $dom->loadXML($xml);

        if (!empty($filename)) {
            $dom->save($filename);
            return;
        }

        return $dom->saveXML();
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