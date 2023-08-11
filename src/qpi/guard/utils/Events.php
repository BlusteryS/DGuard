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
	public function onBreak(BlockBreakEvent $e) {
		$p = $e->getPlayer();
		$n = $p->getName();
		$block = $e->getBlock();
		$region = Methods::getInstance()->getRegion($block->getPosition()->getX(), $block->getPosition()->getZ(), $p->getWorld()->getDisplayName());

		if ($region !== "") {
			if (!Server::getInstance()->isOp($p->getName())) {
				$role = Methods::getInstance()->getRole($n, $region);
				if ($role === 0) {
					$e->cancel();
					$p->sendTip("§c§lУ вас нет доступа к этой территории§r§f");
				} elseif ($role === 1) {
					$e->cancel();
					$p->sendTip("§c§lВам не разрешено здесь строить§r§f");
				}
			}
		}
	}

	public function onPlace(BlockPlaceEvent $e) {
		$p = $e->getPlayer();
		$n = $p->getName();

		foreach ($e->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
			$region = Methods::getInstance()->getRegion($block->getPosition()->getX(), $block->getPosition()->getZ(), $p->getWorld()->getDisplayName());

			if ($region !== "") {
				if (!Server::getInstance()->isOp($p->getName())) {
					$role = Methods::getInstance()->getRole($n, $region);
					if ($role === 0) {
						$e->cancel();
						$p->sendTip("§c§lУ вас нет доступа к этой территории§r§f");
					} elseif ($role === 1) {
						$e->cancel();
						$p->sendTip("§c§lВам не разрешено здесь строить§r§f");
					}
				}
			}
		}
	}

	public function onTap(PlayerInteractEvent $e) {
		$p = $e->getPlayer();
		$n = $p->getName();
		$block = $e->getBlock();
		$id = $block->getTypeId();
		$itemHand = $p->getInventory()->getItemInHand()->getTypeId();

		$blocked_items = [Item::FLINT_AND_STEEL, Item::BUCKET, Item::WOODEN_SHOVEL, Item::STONE_SHOVEL, Item::GOLDEN_SHOVEL, Item::DIAMOND_SHOVEL, Item::NETHERITE_SHOVEL, Item::WOODEN_HOE, Item::STONE_HOE, Item::GOLDEN_HOE, Item::DIAMOND_HOE, Item::NETHERITE_HOE];
		$blocked_blocks = [Block::ACACIA_TRAPDOOR, Block::BIRCH_TRAPDOOR, Block::CRIMSON_TRAPDOOR, Block::JUNGLE_TRAPDOOR, Block::IRON_TRAPDOOR, Block::DARK_OAK_TRAPDOOR, Block::SPRUCE_TRAPDOOR, Block::WARPED_TRAPDOOR, Block::JUNGLE_TRAPDOOR, Block::MANGROVE_TRAPDOOR, Block::ACACIA_FENCE_GATE, Block::BIRCH_FENCE_GATE, Block::CRIMSON_FENCE_GATE, Block::JUNGLE_FENCE_GATE, Block::DARK_OAK_FENCE_GATE, Block::SPRUCE_FENCE_GATE, Block::WARPED_FENCE_GATE, Block::JUNGLE_FENCE_GATE, Block::MANGROVE_FENCE_GATE];

		if (($itemHand === Item::STICK || $itemHand === Item::WOODEN_AXE) && $e->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
			if ($itemHand === Item::STICK) {
				$region = Methods::getInstance()->getRegion($block->getPosition()->getX(), $block->getPosition()->getZ(), $p->getWorld()->getDisplayName());

				if ($region !== "") {
					Forms::getInstance()->f_regions_info($p, $region);
				} else $p->sendMessage("§c§l>§f  В данном месте нет регионов.§r");

			} else {
				$n = strtolower($n);
				if (isset(DGuard::getInstance()->wand[$n])) {
					if (DGuard::getInstance()->wand[$n]) {
						DGuard::getInstance()->set_pos(TRUE, $block->getPosition()->getX(), $block->getPosition()->getZ(), $p->getWorld()->getDisplayName(), $p);
					} else {
						DGuard::getInstance()->set_pos(FALSE, $block->getPosition()->getX(), $block->getPosition()->getZ(), $p->getWorld()->getDisplayName(), $p);
					}
				} else {
					DGuard::getInstance()->set_pos(TRUE, $block->getPosition()->getX(), $block->getPosition()->getZ(), $p->getWorld()->getDisplayName(), $p);
				}
			}

			$e->cancel();
		} elseif (($blockedBlock = in_array($id, $blocked_blocks) || in_array($itemHand, $blocked_items)) && !Server::getInstance()->isOp($p->getName())) {
			$region = Methods::getInstance()->getRegion($block->getPosition()->getX(), $block->getPosition()->getZ(), $p->getWorld()->getDisplayName());
			if ($region !== "") {
				$role = Methods::getInstance()->getRole($n, $region);
				if ($role === 0) {
					if ($id === Block::CHEST || $id === Block::TRAPPED_CHEST) {
						if (Methods::getInstance()->getFlag($region, "chest") === "deny") $e->cancel();
					} elseif (($id === Block::FURNACE || $id === Block::BLAST_FURNACE) && Methods::getInstance()->getFlag($region, "furnace") === "deny") {
						$e->cancel();
					} elseif ($blockedBlock) {
						$e->cancel();
					}
				}

				if (in_array($itemHand, $blocked_items)) {
					if ($role < 2) $e->cancel();
				}
			}
		}
	}

	public function onDamage(EntityDamageEvent $e) {
		if ($e instanceof EntityDamageByEntityEvent) {
			$p = $e->getEntity();

			if ($p instanceof Player) {
				$region = Methods::getInstance()->getRegion($p->getPosition()->getX(), $p->getPosition()->getZ(), $p->getWorld()->getDisplayName());
				if ($region !== "") {
					if ($e->getDamager() instanceof Player) {
						if (Methods::getInstance()->getFlag($region, "pvp") === "deny") $e->cancel();
					}
				}
			} else {
				$region = Methods::getInstance()->getRegion($p->getPosition()->getX(), $p->getPosition()->getZ(), $p->getWorld()->getDisplayName());
				if ($region !== "") {
					if (Methods::getInstance()->getFlag($region, "pve") === "deny") $e->cancel();
				}
			}
		}
	}
}
