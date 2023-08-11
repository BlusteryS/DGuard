<?php

declare(strict_types=1);

namespace qpi\guard\elements;

use qpi\guard\DGuard;

class Flag {
	private array $data;

	private function __construct($name, $title, $default) {
		$this->data = [
			"name" => $name,
			"title" => $title,
			"default" => $default
		];
	}

	public static function registerFlag($name, $title, $default = "deny"): void {
		DGuard::$flags[strtolower($name)] = new Flag($name, $title, $default);
	}

	public static function getFlag($name) {
		return DGuard::$flags[strtolower($name)];
	}

	public function __toString(): string {
		return strtolower($this->data["name"]);
	}

	public function getName(): string {
		return $this->data["name"];
	}

	public function getTitle(): string {
		return $this->data["title"];
	}

	public function getDefault(): string {
		return $this->data["default"];
	}
}