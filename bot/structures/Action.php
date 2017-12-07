<?php

namespace w\Bot\structures;

use Slack\DataObject;

class Action extends DataObject
{
    /**
     * Creates a new message attachment.
     * @param string $name The attachment title.
     * @param string $text The attachment body text.
     * @param string $type
     * @param string $value
     * @param string|null $style
     */
    public function __construct($name, $text, $type, $value, $style = null)
    {
        $this->data['name'] = $name;
        $this->data['text'] = $text;
        $this->data['type'] = $type;
        $this->data['value'] = $value;
        if (!is_null($style)) {
            $this->data['style'] = $style;
        }
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->data['value'];
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->data['type'];
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->data['name'];
    }
}