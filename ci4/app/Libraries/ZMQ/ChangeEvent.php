<?php namespace App\Libraries\ZMQ;

class ChangeEvent {

    public ?array $previous;
    public array $next;
    public ?array $extra;

    public static function Parse(array $data): ChangeEvent {
        return new ChangeEvent($data['previous'], $data['next'], $data['extra'] ?? null);
    }

    public function __construct(?array $previous, array $next, ?array $extra = null) {
        $this->previous = $previous;
        $this->next = $next;
        $this->extra = $extra;
    }

    public function getDiff(): array {
        if (isset($this->previous) && is_array($this->previous)) {
            return array_merge(array_diff_assoc($this->previous, $this->next), array_diff_assoc($this->next, $this->previous));
        } else {
            return $this->next;
        }
    }

    public function toArray(): array {
        return [
            'previous' => $this->previous,
            'next' => $this->next,
            'extra' => $this->extra ?? null,
        ];
    }

}
