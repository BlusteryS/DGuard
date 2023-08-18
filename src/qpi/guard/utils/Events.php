<?php

declare(strict_types=1);

namespace qpi\guard\utils;

use pocketmine\block\BlockTypeIds as Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\ItemTypeIds as Item;
use pocketmine\player\Player;
use pocketmine\Server;
use qpi\guard\DGuard;
use function in_array;

class Events implements Listener {
	private array $blocked_items = [
		Item::FLINT_AND_STEEL,
		Item::BUCKET,
		Item::WOODEN_SHOVEL,
		Item::STONE_SHOVEL,
		Item::GOLDEN_SHOVEL,
		Item::DIAMOND_SHOVEL,
		Item::NETHERITE_SHOVEL,
		Item::WOODEN_HOE,
		Item::STONE_HOE,
		Item::GOLDEN_HOE,
		Item::DIAMOND_HOE,
		Item::NETHERITE_HOE
	];

	private array $blocked_blocks = [Block::ACACIA_TRAPDOOR,
		Block::BIRCH_TRAPDOOR,
		Block::CRIMSON_TRAPDOOR,
		Block::JUNGLE_TRAPDOOR,
		Block::IRON_TRAPDOOR,
		Block::DARK_OAK_TRAPDOOR,
		Block::SPRUCE_TRAPDOOR,
		Block::WARPED_TRAPDOOR,
		Block::JUNGLE_TRAPDOOR,
		Block::MANGROVE_TRAPDOOR,
		Block::ACACIA_FENCE_GATE,
		Block::BIRCH_FENCE_GATE,
		Block::CRIMSON_FENCE_GATE,
		Block::JUNGLE_FENCE_GATE,
		Block::DARK_OAK_FENCE_GATE,
		Block::SPRUCE_FENCE_GATE,
		Block::WARPED_FENCE_GATE,
		Block::JUNGLE_FENCE_GATE,
		Block::MANGROVE_FENCE_GATE
	];

	public function onBreak(BlockBreakEvent $event): void {
		$player = $event->getPlayer();
		$block = $event->getBlock();
		$region = Methods::getInstance()->getRegion($block->getPosition()->getFloorX(), $block->getPosition()->getFloorZ(), $player->getWorld()->getDisplayName());

		if ($region !== "") {
			if (!Server::getInstance()->isOp($player->getName())) {
				$role = Methods::getInstance()->getRole($player->getName(), $region);
				if ($role === 0) {
					$event->cancel();
					$player->sendTip("§c§lУ вас нет доступа к этой территории§r§f");
				} elseif ($role === 1) {
					$event->cancel();
					$player->sendTip("§c§lВам не разрешено здесь строить§r§f");
				}
			}
		}
	}

	public function onPlace(BlockPlaceEvent $event): void {
		$player = $event->getPlayer();

		foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
			$region = Methods::getInstance()->getRegion($block->getPosition()->getX(), $block->getPosition()->getZ(), $player->getWorld()->getDisplayName());

			if ($region !== "") {
				if (!Server::getInstance()->isOp($n = $player->getName())) {
					$role = Methods::getInstance()->getRole($n, $region);
					if ($role === 0) {
						$event->cancel();
						$player->sendTip("§c§lУ вас нет доступа к этой территории§r§f");
					} elseif ($role === 1) {
						$event->cancel();
						$player->sendTip("§c§lВам не разрешено здесь строить§r§f");
					}
				}
			}
		}
	}

	public function onTap(PlayerInteractEvent $event): void {
		$player = $event->getPlayer();
		$block = $event->getBlock();
		$id = $block->getTypeId();
		$itemHand = $player->getInventory()->getItemInHand()->getTypeId();

		if (($itemHand === Item::STICK || $itemHand === Item::WOODEN_AXE) && $event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
			if ($itemHand === Item::STICK) {
				$region = Methods::getInstance()->getRegion($block->getPosition()->getX(), $block->getPosition()->getZ(), $player->getWorld()->getDisplayName());

				if ($region !== "") {
					Forms::getInstance()->f_regions_info($player, $region);
				} else $player->sendMessage("§c§l>§f  В данном месте нет регионов.§r");

			} else {
				$n = strtolower($player->getName());
				if (isset(DGuard::getInstance()->wand[$n])) {
					if (DGuard::getInstance()->wand[$n]) {
						DGuard::getInstance()->set_pos(TRUE, $block->getPosition()->getX(), $block->getPosition()->getZ(), $player->getWorld()->getDisplayName(), $player);
					} else {
						DGuard::getInstance()->set_pos(FALSE, $block->getPosition()->getX(), $block->getPosition()->getZ(), $player->getWorld()->getDisplayName(), $player);
					}
				} else {
					DGuard::getInstance()->set_pos(TRUE, $block->getPosition()->getX(), $block->getPosition()->getZ(), $player->getWorld()->getDisplayName(), $player);
				}
			}

			$event->cancel();
		} elseif ((in_array($id, $this->blocked_blocks) || in_array($itemHand, $this->blocked_items)) && !Server::getInstance()->isOp($player->getName())) {
			$region = Methods::getInstance()->getRegion($block->getPosition()->getX(), $block->getPosition()->getZ(), $player->getWorld()->getDisplayName());
			if ($region !== "") {
				$role = Methods::getInstance()->getRole($player->getName(), $region);
				if ($role === 0) {
					if ($id === Block::CHEST || $id === Block::TRAPPED_CHEST) {
						if (Methods::getInstance()->getFlag($region, "chest") === "deny") $event->cancel();
					} elseif (($id === Block::FURNACE || $id === Block::BLAST_FURNACE) && Methods::getInstance()->getFlag($region, "furnace") === "deny") {
						$event->cancel();
					} elseif (Methods::getInstance()->getFlag($region, "interact") === "deny") {
						$event->cancel();
					}
				}

				if (in_array($itemHand, $this->blocked_items)) {
					if ($role < 2) $event->cancel();
				}
			}
		}
	}

	public function onDamage(EntityDamageEvent $event): void {
		if ($event instanceof EntityDamageByEntityEvent) {
			$player = $event->getEntity();

			if (($region = Methods::getInstance()->getRegion($player->getPosition()->getX(), $player->getPosition()->getZ(), $player->getWorld()->getDisplayName())) === "") return;

			if ($player instanceof Player && $event->getDamager() instanceof Player) {
				if (Methods::getInstance()->getFlag($region, "pvp") === "deny") {
					$event->cancel();
				}
			} elseif (Methods::getInstance()->getFlag($region, "mob") === "deny") {
				$event->cancel();
			}
		}
	}
}
