<?php

/**
* © R4KT, Skull3x, and Muqsit.
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Lesser General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version. Additional permission of
* the authors may be required.
*
*/

namespace R4KT;

use onebone\economyapi\EconomyAPI;

use pocketmine\item\{Item, enchantment\Enchantment};
use pocketmine\utils\{Config, TextFormat as TF};
use pocketmine\plugin\PluginBase;
use pocketmine\command\{Command, CommandSender};
use pocketmine\Player;

class AuctionHouse extends PluginBase {

    protected $auctions = [];
    protected $bids = [];

    public function onLoad() {
       @mkdir($this->getDataFolder());
        $fileExistancy = array('config.yml', 'auctions.json', 'bids.json');
        foreach ($fileExistancy as $file) {
            if (!file_exists($this->getDataFolder() . $file)) {
                file_put_contents($this->getDataFolder() . $file, $this->getResource($file));
            }
        }
    }

    /**
    * When the plugin is fully loaded.
    */
    public function onEnable() {
        $this->loadEverything();
    }

    /**
    * When the plugin gets disabled.
    */
    public function onDisable() {
        $this->saveEverything();
    }

    /**
    * Saves everything in 'auctions.json' file
    * before disabling the plugin to avoid data
    * loss.
    */
    public function saveEverything() {
        unlink($this->getDataFolder().'bids.json'); //Avoiding duplication glitches.
        unlink($this->getDataFolder().'auctions.json'); //Avoiding duplication glitches.
        $data = new Config($this->getDataFolder() . 'auctions.json', Config::JSON);
        $data2 = new Config($this->getDataFolder() . 'bids.json', Config::JSON);
        $files = array($data, $data2);
        foreach ($data->getAll() as $aucId => $aucData) {
            $this->auctions[$aucId] = $aucData;
            $data2->set($bidId, $bidData);
        }
        foreach ($data2->getAll() as $bidId => $bidData)
            $this->bids[$bidId] = $bidData;
        }
        $files->save();
    }

    /**
    * Loads everything that was saved in
    * 'auctions.json' and 'bids.json'.
    */
    public function loadEverything()
    {
        $data = new Config($this->getDataFolder() . 'auctions.json', Config::JSON);
        $data2 = new Config($this->getDataFolder() . 'bids.json', Config::JSON);
        foreach ($data->getAll() as $aucId => $aucData) {
            $this->auctions[$aucId] = $aucData;
        }
        foreach ($data2->getAll() as $bidId => $bidData) {
            $this->bids[$bidId] = $bidData;
        }
    }

    /**
    * Prefix used for AuctionHouse messages.
    */
    public static function prefix($positive = true) : string {
        $colour = $positive ? TF::GREEN : TF::RED;
        return TF::AQUA.TF::BOLD.'AH '.TF::RESET.$colour;
    }

    public function sendAuctionList(Player $player) {
        $i = 0;
        foreach ($this->auctions as $id => $data) {
            if ($i > 10) return true;
            $player->sendMessage(TF::AQUA.'ID'.$id.'  =>  '.TF::YELLOW.$data['name'].'(x'.$data['count'].') for $'.$data['price'].' by '.TF::GREEN.$data['seller']);
            ++$i;
        }
    }
    
    public function sendBidList(Player $player) {
        $b = 0;
        foreach ($this->bids as $id => $data) {
            if ($b > 10) return true;
            $player->sendMessage(TF::AQUA.'ID'.$id.'  =>  '.TF::YELLOW.$data['bidder'].' for $'.$data['price'].' by '.TF::GREEN.$data['bidder']);
            ++$b;
        }
    }

    /**
    * Sends '/ah help' message to $player.
    * Player $player.
    */
    public static function sendHelp(Player $player) {
        $border = str_repeat(TF::GOLD.'='.TF::GREEN.'-', 7).TF::GOLD.'=';
        $helps = [
            '/ah list' => 'List all current auctions.',
            '/ah list <player>' => 'List all auctions hosted by <player>',
            '/ah buy <auctionID>' => 'Buy an item off auction',
            '/ah sell <price>' => 'Sell your item in hand.',
            '/ah info <auctionID>' => 'Get detailed information of an item.'
        ];
        $player->sendMessage($border);
        foreach ($helps as $cmd => $desc) {
            $player->sendMessage(TF::AQUA.$cmd.' '.TF::WHITE.$desc);
        }
        $player->sendMessage($border);
        return true;
    }

    /**
    * To sell $item on auction as $player for a $price.
    * Item $item, Player $player, int $price.
    */
    public function sellAuction($player, $item, $price) {
        $aucId = Utils::getFreeKey($this->auctions);
        $name = strtolower($player->getName());
        $cloned = Item::get($item->getId(), $item->getDamage());
        $itemname = $cloned->getName();
        $itemcount = $item->getCount();
        $itemens = $item->hasEnchantments() ? $item->getEnchantments() : null;
        $cname = $item->hasCustomName() ? $item->getName() : null;
        $ens = [];
        if (!is_null($itemens)) {
            foreach ($itemens as $en) {
                $ide = $en->getId();
                $level = $en->getLevel();
                $ens[] = [$ide, $level];
            }
        } else $ens = null;
        $auctiondata = [
            'id' => $item->getId(),
            'damage' => $item->getDamage(),
            'count' => $item->getCount(),
            'enchants' => $ens,
            'name' => $itemname,
            'customname' => $cname,
            'seller' => $name,
            'price' => $price
        ];
        $this->auctions[$aucId] = $auctiondata;
        $player->sendMessage(self::prefix().'You have successfully placed your '.$itemname.' (x'.$itemcount.') for $'.$price.' on auction.');
        $player->sendMessage(TF::GRAY.'Your auction ID is '.TF::GREEN.$aucId.TF::GRAY.'.');
        return true;
    }

    /**
    * Construct an item from $auction.
    * $auction is an existing key in $this->auctions.
    * @return Item.
    */
    public static function constructItem($auction) : Item {
        $itemId = $auction['id'];
        $itemdmg = $auction['damage'];
        $itemcnt = $auction['count'];
        $itemens = $auction['enchants'];
        $itemname = $auction['customname'];
        $item = Item::get($itemId, $itemdmg, $itemcnt);
        if (!is_null($itemname)) {
            $item->setCustomName($itemname);
        }
        if (!is_null($itemens)) {
            foreach ($itemens as $enchant) {
                $en = Enchantment::getEnchantment($enchant[0]);
                $en->setLevel($enchant[1]);
                $item->addEnchantment($en);
            }
        }
        return $item;
    }

    /**
    * To buy an item off auction.
    * Auction can be traced through $aucId.
    * int $aucId, Player $player.
    */
    public function buyAuction(int $aucId, Player $player) {
        if (isset($this->auctions[$aucId])) {
            $auction = $this->auctions[$aucId];
            $itemprice = $auction['price'];
            $money = EconomyAPI::getInstance()->myMoney($player);

            if ($money <= $itemprice) {
                $player->sendMessage(self::prefix(false)."You don't have enough money to buy this item.");
                return false;
            } else EconomyAPI::getInstance()->reduceMoney($player, $auction['price']);

            $seller = $this->getServer()->getPlayer($auction['seller']);
            EconomyAPI::getInstance()->addMoney($seller, $auction['price']);
            $item = self::constructItem($auction);
            $player->getInventory()->addItem($item);
            $player->sendMessage(self::prefix().'You have successfully purchased the item off auction.');
            if ($seller instanceof Player) {
               $seller->sendMessage(self::prefix().$player->getName().' has purchased your item ('.$auction['name'].') for $'.$auction['price']);
               return true;
            }
            unset($this->auctions[$aucId]);
        } else {
            $player->sendMessage(self::prefix(false).'The auction with the ID ('.$aucId.') cannot be found.');
            return true;
        }
    }
    
    public function addBid($player, $price) {
        $name = strtolower($player->getName());
        $auction = $this->auctions[$aucId];
        $biddata = [
            'bidder' => $name,
            'amount' => $price
        ];
        $this->bids[$bidId] = $biddata;
        $player->sendMessage(self::prefix().'You have successfully placed a bid of $'.$price);
        
        if (isset($this->bids[$bidId])) {
            $bid = $this->bids[$bidId];
            $amount = $biddata['amount'];
            $money = EconomyAPI::getInstance()->myMoney($player);

            if ($money <= Data::MINIMUM_BID_AMOUNT) {
                $player->sendMessage(self::prefix(false)."The minimum auction bidding amount is ". Data::MINIMUM_BID_AMOUNT);
                return false;
            } else EconomyAPI::getInstance()->reduceMoney($player, $biddata['amount']);

            $bidder = $this->getServer()->getPlayer($biddata['bidder']);
            $seller = $this->getServer()->getPlayer($auction['seller']);
            $player->sendMessage(self::prefix().'You have successfully placed a bid on that auction.');
            if ($bidder instanceof Player and $seller instanceof Player) {
               $seller->sendMessage(self::prefix().$bidder->getName().' has placed a bid of '.$biddata['amount'].' on your auction.');
                return true;
            }
            unset($this->auctions[$aucId]);
            unset($this->bids[$bidId]);
        } else {
            $player->sendMessage(self::prefix(false).'The auction with the ID ('.$aucId.') cannot be found.');
            return true;
        }
    }

    /**
    * Auction commands.
    */
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool {
        if (strtolower($command->getName()) === 'ah') {
            if (count($args) === 0) {
                self::sendHelp($sender);
                return true;
            } else {
                switch (strtolower($args[0])) {
                    case 'help':
                        self::sendHelp($sender);
                        return true;
                        break;
                    case 'sell':
                        if (isset($args[1])) {
                            if (($item = $sender->getInventory()->getItemInHand())->getId() !== 0) {
                                $this->sellAuction($sender, $item, $args[1]);
                                $sender->getInventory()->remove($item);
                                return true;
                            }
                        } else {
                            $sender->sendMessage(TF::AQUA.'/ah sell '.TF::GRAY.'<price>'.PHP_EOL.TF::GRAY.'Put the item you are currently holding, in auction for '.TF::YELLOW.'$<price>');
                            return true;
                        }
                        break;
                    case 'bid':
                        if (isset($args[1])) {
                                $this->addBid($args[1], $sender);
                                return true;
                            }
                        } else {
                            $sender->sendMessage(TF::AQUA.'/ah bid '.TF::GRAY.'<auctionID> <amount>'.PHP_EOL.TF::GRAY.'Place a bid in auction for '.TF::YELLOW.'$<amount>');
                            return true;
                        }
                    case 'buy':
                        if (isset($args[1])) {
                            $this->buyAuction($args[1], $sender);
                            return true;
                        } else {
                            $sender->sendMessage(TF::AQUA.'/ah buy '.TF::GRAY.'<auctionID>'.PHP_EOL.TF::GRAY.'Buy the item assigned '.TF::YELLOW.'<auctionID>'.TF::GRAY.' off auction.');
                            return true;
                        }
                        break;
                    case 'list':
                        if (!isset($args[1])) {
                            $this->sendAuctionList($sender);
                            $sender->sendMessage(self::prefix().TF::GRAY.'Use '.TF::YELLOW.'/ah list <sellername>'.TF::GRAY.' to find item by seller, '.TF::YELLOW.'/ah info <auctionid> '.TF::GRAY.'to get more information about an item.');
                            return true;
                        } else {
                            if (isset($args[1])) {
                                $expected = strtolower($args[1]);
                                $none = false;
                                foreach ($this->auctions as $id => $data) {
                                    if ($data['seller'] == $expected) {
                                        $sender->sendMessage(TF::AQUA.'ID'.$id.'  =>  '.TF::YELLOW.$data['name'].'(x'.$data['count'].') for $'.$data['price'].' by '.TF::GREEN.$data['seller']);
                                        $none = true;
                                    }
                                }
                                if (!$none) {
                                    $sender->sendMessage(self::prefix(false).$args[1].' does not host any auctions.');
                                    return true;
                                }
                            }
                        }
                        break;
                    case 'info':
                        if (isset($args[1]) && is_numeric($args[1])) {
                            $id = $args[1];
                            if (isset($this->auctions[$id])) {
                                $auc = $this->auctions[$id];
                                $cname = explode(PHP_EOL, $auc['customname']);
                                $bb = TF::YELLOW.'[*]'.TF::DARK_GRAY.str_repeat('=', 30).TF::YELLOW.'[*]';
                                $used = $auc['damage'] > 0 ? 'Yes' : 'No';
                                $enchanted = isset($auc['enchant']) ? 'Yes' : 'No';

                                $sender->sendMessage($bb);
                                $sender->sendMessage(TF::AQUA.'Item: '.TF::GREEN.$auc['name'].TF::DARK_GRAY.' / '.TF::GREEN.$cname[0].TF::RESET.TF::GREEN.'(x'.$auc['count'].')'.PHP_EOL.
                                    TF::AQUA.'Used Item: '.$used.PHP_EOL.
                                    TF::AQUA.'Enchanted: '.$enchanted.PHP_EOL.
                                    TF::AQUA.'Cost: $'.TF::GREEN.$auc['price'].PHP_EOL.
                                    TF::AQUA.'Seller: '.TF::GREEN.$auc['seller']);
                                $sender->sendMessage($bb);
                                return true;

                            } else {
                                $sender->sendMessage(self::prefix(false).'The provided auction cannot be found.');
                                return true;
                            }
                        } else {
                            $sender->sendMessage(TF::AQUA.'/ah info '.TF::GRAY.'<auctionID>');
                            return true;
                        }
                        break;
                }
            }
        }
    }
}
