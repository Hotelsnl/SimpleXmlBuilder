# SimpleXmlBuilder
Extended SimpleXMLElement with array parsing and adding CDATA

This package adds some convenience functions to SimpleXMLElement.

* Strings containing XML special characters will be captioned in a 
CDATA element.

* Modified asXml() to accept an extra parameter for outputting the long 
format, increasing readability.

```php
    public mixed SimpleXMLElement::asXML ([ string $filename ], [ boolean] $longOutput)
```

* Using a associative PHP array to generate an XML message.
  The special keys `@attributes` and `@namespace` can be added to add 
  attributes to the parent element.

# Usage

```php
use \HotelsNL\SimpleXmlBuilder\SimpleXmlBuilder;

$document = array(
    'listings' => array(
        '@attributes' => array(
            'xmlns:xsi' => "http://www.w3.org/2001/XMLSchema-instance",
            'xsi:noNamespaceSchemaLocation' => "http://local.google.com/local_feed.xsd"
        ),
        '@namespace' => 'http://www.w3.org/2001/XMLSchema-instance',
        'listing' => array(
            array(
                'id' => 1,
                'language' => 'nl',
                'datum' => 'WGS84',
                'name' => 'Grand hotel In Den Tux',
                'description' => 'it\'s awesome sauce >< & fitting for a king |^^^|.',
                'features' => array(
                    'crown' => 'gold',
                    'gender' => 'male',
                    'temper' => 'angry'
                )
            ),
            array(
                'id' => 2,
                'language' => 'en',
                'datum' => 'WGS84',
                'name' => 'Grand hotel Windows',
                'description' => 'it\'s awesome sauce >< & fitting for a queen _^_.',
                'features' => array(
                    'crown' => 'diamond',
                    'gender' => 'female',
                    'temper' => 'loving'
                )
            ),
            array(
                'id' => 3,
                'language' => 'de',
                'datum' => 'WGS84',
                'name' => 'Feature stay at Apple',
                'description' => 'it\'s awesome sauce >< & fitting for a hipster |www|.',
                'features' => array(
                    'crown' => 'hair, worn on chin',
                    'gender' => 'male',
                    'temper' => 'chill'
                )
            ),
        )
    )
);

$xml = SimpleXmlBuilder::createXML($document);
echo $xml->asXML(null, true);

```

The above code will output:
```xml
<?xml version="1.0" encoding="UTF-8"?>
<listings xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="http://local.google.com/local_feed.xsd">
  <listing>
    <id>1</id>
    <language>nl</language>
    <datum>WGS84</datum>
    <name>Grand hotel In Den Tux</name>
    <description><![CDATA[it's awesome sauce >< & fitting for a king |^^^|.]]></description>
    <features>
      <crown>gold</crown>
      <gender>male</gender>
      <temper>angry</temper>
    </features>
  </listing>
  <listing>
    <id>2</id>
    <language>en</language>
    <datum>WGS84</datum>
    <name>Grand hotel Windows</name>
    <description><![CDATA[it's awesome sauce >< & fitting for a queen _^_.]]></description>
    <features>
      <crown>diamond</crown>
      <gender>female</gender>
      <temper>loving</temper>
    </features>
  </listing>
  <listing>
    <id>3</id>
    <language>de</language>
    <datum>WGS84</datum>
    <name>Feature stay at Apple</name>
    <description><![CDATA[it's awesome sauce >< & fitting for a hipster |www|.]]></description>
    <features>
      <crown>hair, worn on chin</crown>
      <gender>male</gender>
      <temper>chill</temper>
    </features>
  </listing>
</listings>
```