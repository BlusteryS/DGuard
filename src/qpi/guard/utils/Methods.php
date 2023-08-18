<?php

declare(strict_types=1);

namespace qpi\guard\utils;

use qpi\guard\DGuard;
use qpi\guard\elements\Flag;

class Methods {
	private static Methods $instance;

	public function __construct() {
		self::$instance = $this;
	}

	public function isPrivated(int $x, int $z, string $level): bool {
		$areas = DGuard::getInstance()->areas->getAll();

		foreach ($areas as $name => $body) {
			if ($level === $body["level"]) {
				if ($body["minX"] <= $x && $x <= $body["maxX"]) {
					if ($body["minZ"] <= $z && $z <= $body["maxZ"]) {
						return TRUE;
					}
				}
			}
		}
		return FALSE;
	}

	public static function getInstance(): Methods {
		return self::$instance;
	}

	public function setFlag(string $region, string $flag, string $value): void {
		$areas = DGuard::getInstance()->areas->getAll();

		$areas[strtolower($region)]["flags"][strtolower($flag)] = $value;

		DGuard::getInstance()->areas->setAll($areas);
		DGuard::getInstance()->areas->save();
	}

	public function getFlag(string $region, string $flag): string {
		$areas = DGuard::getInstance()->areas->getAll();

		if (!isset($areas[strtolower($region)]["flags"][strtolower($flag)])) {
			$areas[strtolower($region)]["flags"][strtolower($flag)] = "deny";
			DGuard::getInstance()->areas->setAll($areas);
			DGuard::getInstance()->areas->save();
		}

		return $areas[strtolower($region)]["flags"][strtolower($flag)];
	}

	public function getFlagByCoords(int $x, int $z, string $level, string $flag): string {
		$areas = DGuard::getInstance()->areas->getAll();

		foreach ($areas as $body) {
			if ($level === $body["level"]) {
				if ($body["minX"] <= $x && $x <= $body["maxX"]) {
					if ($body["minZ"] <= $z && $z <= $body["maxZ"]) {
						$region = $body;
						unset($areas);
						break;
					}
				}
			}
		}

		if (isset($region)) {
			return $region["flags"][strtolower($flag)] ?? "allow";
		}

		return "";
	}

	public function createRegion(string $region, string $owner, int $x1, int $z1, int $x2, int $z2, string $level, bool $op = FALSE): int {
		$owner = strtolower($owner);

		$tmp = [];

		if ($x1 > $x2) {
			$tmp["x"] = ["min" => $x2, "max" => $x1,];
		} else {
			$tmp["x"] = ["min" => $x1, "max" => $x2,];
		}

		if ($z1 > $z2) {
			$tmp["z"] = ["min" => $z2, "max" => $z1,];
		} else {
			$tmp["z"] = ["min" => $z1, "max" => $z2,];
		}

		$x1 = $tmp["x"]["min"];
		$x2 = $tmp["x"]["max"];
		$z1 = $tmp["z"]["min"];
		$z2 = $tmp["z"]["max"];
		unset($tmp);

		$areas = DGuard::getInstance()->areas->getAll();

		if (!isset($areas[strtolower($region)])) {
			if (!$this->isPrivatedArea($x1, $z1, $x2, $z2, $level)) {
				$config = DGuard::getInstance()->config->getAll();

				if ($this->getSpace($x1, $z1, $x2, $z2) <= $config["default-space"] || $op) {
					if (count($this->getRegions($owner)) <= $config["default-regions"] || $op) {
						if ($level === $config["main-level"] || $op) {
							$pk = ["name" => strtolower($region), "owner" => $owner, "level" => $level, "members" => [], "guests" => [], "minX" => $x1, "maxX" => $x2, "minZ" => $z1, "maxZ" => $z2, "flags" => [],];

							foreach (DGuard::$flags as $tag => $flag) {
								/* @var $flag Flag */

								$pk["flags"][$tag] = $flag->getDefault();
							}

							$areas[strtolower($region)] = $pk;
							unset($pk);

							DGuard::getInstance()->areas->setAll($areas);
							DGuard::getInstance()->areas->save();

							return 0; //Приват успешно создан.
						}
						return 5; //В данном мире нельзя приватить территории.
					}
					return 4; //Достигнут лимит регионов.
				}
				return 3; //Регион занимает слишком огромную площадь.
			}
			return 2; //Регион пересекает другие регионы.
		}
		return 1; //Регион с таким названием уже существует.
	}

	public function isPrivatedArea(int $x1, int $z1, int $x2, int $z2, string $level): bool {
		$tmp = [];

		if ($x1 > $x2) {
			$tmp["x"] = ["min" => $x2, "max" => $x1,];
		} else {
			$tmp["x"] = ["min" => $x1, "max" => $x2,];
		}

		if ($z1 > $z2) {
			$tmp["z"] = ["min" => $z2, "max" => $z1,];
		} else {
			$tmp["z"] = ["min" => $z1, "max" => $z2,];
		}

		$x1 = $tmp["x"]["min"];
		$x2 = $tmp["x"]["max"];
		$z1 = $tmp["z"]["min"];
		$z2 = $tmp["z"]["max"];
		unset($tmp);

		$areas = DGuard::getInstance()->areas->getAll();

		foreach ($areas as $body) {
			if ($level === $body["level"]) {
				if (!($body["minX"] > $x2 || $body["maxX"] < $x1 || $body["minZ"] > $z2 || $body["maxZ"] < $z1)) return TRUE;
			}
		}
		return FALSE;
	}

	public function getSpace(int $x1, int $z1, int $x2, int $z2): int {
		$tmp = [];

		if ($x1 > $x2) {
			$tmp["x"] = ["min" => $x2, "max" => $x1,];
		} else {
			$tmp["x"] = ["min" => $x1, "max" => $x2,];
		}

		if ($z1 > $z2) {
			$tmp["z"] = ["min" => $z2, "max" => $z1,];
		} else {
			$tmp["z"] = ["min" => $z1, "max" => $z2,];
		}

		$x1 = $tmp["x"]["min"];
		$x2 = $tmp["x"]["max"];
		$z1 = $tmp["z"]["min"];
		$z2 = $tmp["z"]["max"];
		unset($tmp);

		return ($x2 - $x1) * ($z2 - $z1);
	}

	public function getRegions(string $player): array {
		$areas = DGuard::getInstance()->areas->getAll();

		$player = strtolower($player);
		$regions = [];
		foreach ($areas as $name => $body) {
			if ($body["owner"] === $player) $regions[$name] = $body;
		}
		return $regions;
	}

	public function removeRegion(string $region): void {
		$areas = DGuard::getInstance()->areas->getAll();

		unset($areas[strtolower($region)]);

		DGuard::getInstance()->areas->setAll($areas);
		DGuard::getInstance()->areas->save();
	}

	public function isPrivatedName(string $region): bool {
		$areas = DGuard::getInstance()->areas->getAll();
		return isset($areas[strtolower($region)]);
	}

	public function getRegionInfo(string $region): array {
		$areas = DGuard::getInstance()->areas->getAll();
		return $areas[strtolower($region)];
	}

	public function getRegion(int $x, int $z, string $level): string {
		$areas = DGuard::getInstance()->areas->getAll();

		foreach ($areas as $name => $body) {
			if ($level === $body["level"]) {
				if ($body["minX"] <= $x && $x <= $body["maxX"]) {
					if ($body["minZ"] <= $z && $z <= $body["maxZ"]) {
						return $name;
					}
				}
			}
		}
		return "";
	}

	public function setRole(string $player, int $role, string $region): void {
		$areas = DGuard::getInstance()->areas->getAll();

		$player = strtolower($player);
		$region = strtolower($region);
		$old_role = $this->getRole($player, $region);

		switch ($old_role) {
			case 1:
				foreach ($areas[$region]["guests"] as $id => $p) {
					if ($p === $player) unset($areas[$region]["guests"][$id]);
				}
				break;
			case 2:
				foreach ($areas[$region]["members"] as $id => $p) {
					if ($p === $player) unset($areas[$region]["members"][$id]);
				}
				break;
		}

		switch ($role) {
			case 1:
				$areas[$region]["guests"][] = $player;
				break;
			case 2:
				$areas[$region]["members"][] = $player;
				break;
			case 3:
				$areas[$region]["members"][] = $areas[$region]["owner"];
				$areas[$region]["owner"] = $player;
				break;
		}

		DGuard::getInstance()->areas->setAll($areas);
		DGuard::getInstance()->areas->save();
	}

	public function getRole(string $player, string $region): int {
		$areas = DGuard::getInstance()->areas->getAll();

		$player = strtolower($player);
		$area = $areas[strtolower($region)];

		if ($area["owner"] === $player) {
			return 3;
		}

		if (in_array($player, $area["members"])) {
			return 2;
		}

		if (in_array($player, $area["guests"])) {
			return 1;
		}

		return 0;
	}
}