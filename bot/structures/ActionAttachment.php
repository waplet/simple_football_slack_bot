<?php

namespace w\Bot\structures;

use Slack\Message\Attachment;

class ActionAttachment extends Attachment
{
    /**
     * Adds an action to the attachment.
     * @param Action $action
     * @return $this
     */
    public function addAction(Action $action)
    {
        $this->data['actions'][] = $action;
        return $this;
    }

    /**
     * @param string $callbackId
     * @return $this
     */
    public function setCallbackId($callbackId)
    {
        $this->data['callback_id'] = $callbackId;
        return $this;
    }

    public function jsonUnserialize(array $data)
    {
        parent::jsonUnserialize($data);

        if (!isset($this->data['actions'])) {
            return;
        }

        for ($i = 0; $i < count($this->data['actions']); $i++) {
            $this->data['actions'][$i] = Action::fromData($this->data['actions'][$i]);
        }
    }
}