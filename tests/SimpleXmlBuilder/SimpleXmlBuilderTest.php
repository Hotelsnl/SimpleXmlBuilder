<?php
/**
 * This file contains tests for the SimpleXmlBuilder package.
 *
 * @package HotelsNL
 * @subpackage SimpleXmlBuilder
 */

namespace HotelsNL\SimpleXmlBuilder;

/**
 * @covers HotelsNL\SimpleXmlBuilder
 */
class SimpleXmlBuilderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Some valid configuration options.
     */
    public function documentAsArrayProvider()
    {
        $rv = array(
            array(
                array(
                    'listing' => array(
                        '@attributes' => array('type' => 'test')
                    )
                ),
                null,
                '<?xml version="1.0" encoding="UTF-8"?><listing type="test"></listing>'
            ),
            array(
                array(
                    'listing' => array(
                        '@attributes' => array('type' => 'test'),
                        'hotel' => array(
                            '@attributes' => array('status' => 'offline'),
                            'name' => 'hotel1'
                        )
                    )
                ),
                null,
                '<?xml version="1.0" encoding="UTF-8"?><listing type="test"><hotel status="offline"><name>hotel1</name></hotel></listing>'
            ),
            array(
                array(
                    'listing' => array(
                        '@attributes' => array('type' => 'test'),
                        'hotel' => array(
                            array(
                                '@attributes' => array('status' => 'offline'),
                                'name' => 'hotel1'
                            ),
                            array(
                                '@attributes' => array('status' => 'online'),
                                'name' => 'hotel2'
                            )
                        )
                    )
                ),
                null,
                '<?xml version="1.0" encoding="UTF-8"?><listing type="test"><hotel status="offline"><name>hotel1</name></hotel><hotel status="online"><name>hotel2</name></hotel></listing>'
            ),
            array(
                array(
                    'listing' => array(
                        '@attributes' => array('type' => 'test'),
                        'component' => array(
                            array(
                                '@attributes' => array('type' => 'name'),
                                'hotel1'
                            ),
                            array(
                                '@attributes' => array('type' => 'city'),
                                'Groningen'
                            )
                        )
                    )
                ),
                null,
                '<?xml version="1.0" encoding="UTF-8"?><listing type="test"><component type="name">hotel1</component><component type="city">Groningen</component></listing>'
            )
        );

        return $rv;
    }

    /**
     * Test object creation.
     *
     * @dataProvider documentAsArrayProvider
     * @param array $document
     * @param SimpleXmlBuilder $xmlObject
     * @param string $expected
     * @internal param string $paymentInformationId identifier string for the payment
     * @internal param bool $isBatch true if payment is a batch payment.
     */
    public function testObjectCreation(
        array $document,
        SimpleXmlBuilder $xmlObject = null,
        $expected = null
    ) {
        $xmlDocument = SimpleXmlBuilder::createXML($document, $xmlObject);
        $this->assertInstanceOf('\HotelsNL\SimpleXmlBuilder\SimpleXmlBuilder', $xmlDocument);
        $result = $xmlDocument->asXML();
        $this->assertXmlStringEqualsXmlString($expected, $result);
    }
}
