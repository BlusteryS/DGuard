<?php

declare(strict_types=1);

namespace qpi\guard;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use qpi\guard\elements\Flag;
use qpi\guard\utils\Events;
use qpi\guard\utils\Forms;
use qpi\guard\utils\Methods;

class DGuard extends PluginBase implements Listener {
	public const VERSION = 1;
	public static array $flags = [];
	private static self $instance;
	public Config $areas;
	public Config $config;
	public array $pos1 = [];
	public array $pos2 = [];
	public array $wand = [];
	public array $region = [];
	public array $tmp = [];

	public function onEnable(): void {

		self::$instance = $this;
		new Methods();
		new Forms();

		$this->getServer()->getPluginManager()->registerEvents(new Events(), $this);


		$this->areas = new Config($this->getDataFolder() . "areas.json", Config::JSON, []);
		$this->config = new Config($this->getDataFolder() . "settings.json", Config::JSON, ["version" => self::VERSION, "default-space" => 10000, "default-regions" => 3, "main-level" => "world",]);

		Flag::registerFlag("pvp", "Разрешает PVP");
		Flag::registerFlag("chest", "Разрешает всем открывать сундуки");
		Flag::registerFlag("interact", "Разрешает всем взаимодействовать с регионом");
		Flag::registerFlag("furnace", "Разрешает всем использовать печки");
		Flag::registerFlag("mob", "Разрешает бить мобов", "allow");
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $args): bool {
		if (strtolower($command->getName()) === "rg") {
			if ($sender instanceof Player) {
				if (isset($args[0])) {
					switch (strtolower($args[0])) {
						case "pos1":
							$this->set_pos(TRUE, $sender->getPosition()->getX(), $sender->getPosition()->getZ(), $sender->getWorld()->getDisplayName(), $sender);
							break;
						case "pos2":
							$this->set_pos(FALSE, $sender->getPosition()->getX(), $sender->getPosition()->getZ(), $sender->getWorld()->getDisplayName(), $sender);
							break;
						default:
							$sender->sendMessage("§l§c>§e Не найдена суб-команда.§r");
					}
				} else Forms::getInstance()->f_menu($sender);
			} elseif (isset($args[0])) {
				switch (strtolower($args[0])) {
					case "claim":
						if (isset($args[1], $args[2], $args[3], $args[4], $args[5], $args[6])) {
							$x1 = (int)$args[1];
							$x2 = (int)$args[2];
							$z1 = (int)$args[3];
							$z2 = (int)$args[4];
							$region = $args[5];
							$player = $args[6];

							$result = Methods::getInstance()->createRegion($region, $player, $x1, $z1, $x2, $z2, "world", TRUE);

							if ($result === 0) {
								$sender->sendMessage("Регион успешно создан!");
							} elseif ($result === 1) {
								$sender->sendMessage("Название региона занято!");
							} elseif ($result === 2) $sender->sendMessage("Регион пересекает другие регионы!");

						} else $sender->sendMessage("Использование: /rg claim <x1> <x2> <z1> <z2> <Регион> <Владелец>");
						break;
					case "remove":
						if (isset($args[1])) {
							$region = $args[1];

							if (Methods::getInstance()->isPrivatedName($region)) {
								Methods::getInstance()->removeRegion($region);
								$sender->sendMessage("Регион успешно удален.");
							} else $sender->sendMessage("Регион не найден!");

						} else $sender->sendMessage("Использование: /rg remove <Регион>");
						break;
					case "reowner":
						if (isset($args[1], $args[2])) {
							$region = strtolower($args[1]);
							$player = strtolower($args[2]);

							if (Methods::getInstance()->isPrivatedName($region)) {
								$areas = $this->areas->getAll();

								$areas[$region]["owner"] = $player;

								$this->areas->setAll($areas);
								$this->areas->save();
								$sender->sendMessage("Владелец был успешно изменен!");
							} else $sender->sendMessage("Регион не найден!");

						} else $sender->sendMessage("Использование: /rg reowner <Регион> <Новый владелец>");
						break;
					case "help":
						$sender->sendMessage("/rg claim - Заприватить регион.");
						$sender->sendMessage("/rg help - Помощь.");
						$sender->sendMessage("/rg remove - Удалить регион.");
						$sender->sendMessage("/rg reowner - Передать регион.");
						break;
				}
			} else $sender->sendMessage("Введите /rg help для просмотра списка команд.");
		}
		return TRUE;
	}

	public function set_pos(bool $firstPos, $x, $z, $level, Player $player): void {
		if (Methods::getInstance()->isPrivated($x, $z, $level)) {
			$player->sendMessage("§l§c>§e Невозможно здесь установить точку, тк здесь находится регион.§r");
		} elseif ($firstPos) {
			if (isset($this->pos2[strtolower($player->getName())])) {
				$temp = $this->pos2[strtolower($player->getName())];

				if ($temp["x"] === (int)$x && $temp["z"] === (int)$z) return;
			}

			if (isset($this->pos1[strtolower($player->getName())])) {
				$temp = $this->pos1[strtolower($player->getName())];

				if ($temp["x"] === (int)$x && $temp["z"] === (int)$z) return;
			}

			$player->sendMessage("§c§l>§f §3Первая точка§f была установлена. Нажмите еще раз для установки §3второй точки§f.§r");
			$this->pos1[strtolower($player->getName())] = ["x" => (int)$x, "z" => (int)$z,];

			$this->wand[strtolower($player->getName())] = FALSE;
		} else {
			if (isset($this->pos1[strtolower($player->getName())])) {
				$temp = $this->pos1[strtolower($player->getName())];

				if ($temp["x"] === (int)$x && $temp["z"] === (int)$z) return;
			}

			if (isset($this->pos2[strtolower($player->getName())])) {
				$temp = $this->pos2[strtolower($player->getName())];

				if ($temp["x"] === (int)$x && $temp["z"] === (int)$z) return;
			}

			$player->sendMessage("§c§l>§f §3Вторая точка§f была установлена. Теперь можно создать регион.§r");
			$this->pos2[strtolower($player->getName())] = ["x" => (int)$x, "z" => (int)$z,];

			$this->wand[strtolower($player->getName())] = TRUE;
		}
	}

	public static function getInstance(): DGuard {
		return self::$instance;
	}
}
