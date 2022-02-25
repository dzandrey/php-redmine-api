<?php

namespace Redmine\Serializer;

use Exception;
use Redmine\Exception\SerializerException;
use SimpleXMLElement;

/**
 * XmlSerializer
 */
final class XmlSerializer
{
    /**
     * @throws SerializerException if $data is not valid XML
     */
    public static function createFromString(string $data): self
    {
        $serializer = new self();
        $serializer->deserialize($data);

        return $serializer;
    }

    /**
     * @throws SerializerException if $data could not be serialized to XML
     */
    public static function createFromArray(array $data): self
    {
        $serializer = new self();
        $serializer->denormalize($data);

        return $serializer;
    }

    private string $encoded;

    /** @var mixed $normalized */
    private $normalized;

    private SimpleXMLElement $deserialized;

    private function __construct()
    {
        // use factory method instead
    }

    /**
     * @return mixed
     */
    public function getNormalized()
    {
        return $this->normalized;
    }

    public function getEncoded(): string
    {
        return $this->encoded;
    }

    private function deserialize(string $encoded): void
    {
        $this->encoded = $encoded;

        try {
            $this->deserialized = new SimpleXMLElement($encoded);
        } catch (Exception $e) {
            throw new SerializerException(
                'Catched error "' . $e->getMessage() . '" while decoding XML: ' . $encoded,
                $e->getCode(),
                $e
            );
        }

        $this->normalize($this->deserialized);
    }

    private function normalize(SimpleXMLElement $deserialized): void
    {
        try {
            $serialized = json_encode($deserialized, \JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new SerializerException(
                'Catched error "' . $e->getMessage() . '" while encoding SimpleXMLElement',
                $e->getCode(),
                $e
            );
        }

        $this->normalized = JsonSerializer::createFromString($serialized)->getNormalized();
    }

    private function denormalize(array $normalized): void
    {
        $this->normalized = $normalized;

        $key = array_key_first($this->normalized);

        try {
            $this->deserialized = $this->createXmlElement($key, $this->normalized[$key]);
        } catch (Exception $e) {
            throw new SerializerException(
                'Could not create XML from array: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }

        $this->encoded = $this->deserialized->asXml();
    }

    private function createXmlElement(string $key, array $params): SimpleXMLElement
    {
        $xml = new SimpleXMLElement('<?xml version="1.0"?><'.$key.'></'.$key.'>');

        foreach ($params as $k => $v) {
            $this->addChildToXmlElement($xml, $k, $v);
        }

        return $xml;
    }

    private function addChildToXmlElement(SimpleXMLElement $xml, $k, $v): void
    {
        if ('custom_fields' === $k && is_array($v)) {
            $this->attachCustomFieldXML($xml, $v, 'custom_fields', 'custom_field');
        } else {
            $xml->$k = $v;
        }
    }

    /**
     * Attaches Custom Fields to XML element.
     *
     * @param SimpleXMLElement $xml    XML Element the custom fields are attached to
     * @param array            $fields array of fields to attach, each field needs name, id and value set
     *
     * @see http://www.redmine.org/projects/redmine/wiki/Rest_api#Working-with-custom-fields
     */
    private function attachCustomFieldXML(SimpleXMLElement $xml, array $fields, string $fieldsName, string $fieldName): void
    {
        $_fields = $xml->addChild($fieldsName);
        $_fields->addAttribute('type', 'array');
        foreach ($fields as $field) {
            $_field = $_fields->addChild($fieldName);

            if (isset($field['name'])) {
                $_field->addAttribute('name', $field['name']);
            }
            if (isset($field['field_format'])) {
                $_field->addAttribute('field_format', $field['field_format']);
            }
            $_field->addAttribute('id', $field['id']);
            if (array_key_exists('value', $field) && is_array($field['value'])) {
                $_field->addAttribute('multiple', 'true');
                $_values = $_field->addChild('value');
                if (array_key_exists('token', $field['value'])) {
                    foreach ($field['value'] as $key => $val) {
                        $_values->addChild($key, $val);
                    }
                } else {
                    $_values->addAttribute('type', 'array');
                    foreach ($field['value'] as $val) {
                        $_values->addChild('value', $val);
                    }
                }
            } else {
                $_field->value = $field['value'];
            }
        }
    }
}
