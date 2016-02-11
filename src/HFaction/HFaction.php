<?php
/**
 * Author: happy163
 * Notice: the user name must be lowered before used in array
 */

namespace HFaction;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class HFaction extends PluginBase{

    /** @var HFaction */
    private static $instance;
    private $path = null;

    /** @var Config */
    private $conf;
    /** @var [][] */
    private $faction = [];
    private $member = [];

    public static function getInstance() : HFaction{
        return self::$instance;
    }

    public function onLoad(){
    }

    public function onEnable(){
        $this->path = $this->getDataFolder();
        @mkdir($this->path);

        $this->conf = new Config($this->path.'Config.yml', Config::YAML, [
            'async-save'=>false,
            'level-setting'=>[
                'manage'=>5,
                'invite'=>3
            ],
            'member-limit'=>100,
            'default-position'=>[
                'owner'=>10,
                'manager'=>6,
                'normal'=>1
            ],
            'allowed-map'=>[]
        ]);

        $this->getLogger()->debug(TextFormat::GOLD . "Factions is loading...");
        $this->loadFactions();
        $this->getLogger()->debug(TextFormat::GREEN . "Factions Loaded");

        //$this->test2();
        //$this->test();
    }

    public function onDisable(){
        $this->save();
    }

    private function loadFactions(){
        //$factions[$name] = ['name'=>$name, 'creator'=>$creator, 'position'=>['owner'=>10, 'manager'=>6, 'normal'=>1], 'level'=>1, 'money'=>100];
        //$data[$faction][$name] = ['name'=>$name, 'faction'=>$faction, 'position'=>'normal', 'level'=>1, 'contribution'=>0];
        $config = new Config($this->path.'Faction.yml', Config::YAML, []);
        foreach($config->getAll() as $key=>$value){
            if(isset($value['owner']) and isset($value['level']) and isset($value['money']) and is_numeric($value['level']) and is_numeric($value['money']) and isset($value['position']) and is_array($value['position'])){
                $this->faction[$key] = $value;
            }
        }
        $config = new Config($this->path.'Member.yml', Config::YAML, []);
        foreach($this->faction as $faction=>$data){
            $this->member[$faction] = [];
            foreach($config->get($faction, []) as $key=>$value){
                if(array_key_exists('level', $value) and array_key_exists('contribution', $value) and array_key_exists('position', $value) and is_numeric($value['level']) and is_numeric($value['contribution'])){
                        $this->member[$faction][strtolower($key)] = $value;
                }
            }
        }
    }

    public function getFactions(){
        return $this->faction;
    }

    public function getFaction($faction){
        return array_key_exists($faction, $this->faction) ? $this->faction[$faction] :null;
    }
    public function getFactionMembers($faction, $data = true){
        if(array_key_exists($faction, $this->faction)){
            return $data ? $this->member[$this->member[$faction]] : array_keys($this->member);
        }
        return null;
    }

    public function getFactionMember($faction, $member){
        if(array_key_exists($faction, $this->member)){
            if(array_key_exists($member, $this->member[$faction])){
                return $this->member[$faction][$member];
            }
        }
        return null;
    }

    public function getMembers($data = true){
        if($data){
            return $this->member;
        }
        $result = [];
        foreach($this->member as $faction=>$array){
            $result[$faction] = array_keys($array);
        }
        return $result;
    }

    public function getMember($name){
        foreach($this->member as $faction=>$array){
            $array = array_change_key_case($array);
            if(array_key_exists(strtolower($name), $array)){
                $data = $array[strtolower($name)];
                $data['faction'] = $faction;
                $data['name'] = $name;
                return $data;
            }
        }
        return null;
    }

    public function creatorFaction($name, $creator, $money = 0) : bool{
        if(!is_string($name)){
            return false;
        }
        if(array_key_exists($name, $this->faction)){
            return false;
        }
        if(!is_numeric($money)){
            $money = 0;
        }
        $data = $this->getMember($creator);
        if($data != null){
            return false;
        }
        $faction = ['owner'=>$creator, 'position'=>$this->conf->get('default-position', ['owner'=>10, 'manager'=>6, 'normal'=>1]), 'level'=>1, 'money'=>$money];
        $this->faction[$name] = $faction;
        $this->member[$name][$creator] = ['position'=>'owner', 'level'=>0, 'contribution'=>$money];
        return true;
    }

    public function removeFaction($name, $owner)
    {
        $faction = $this->getFaction($name);
        if (is_array($faction)) {
            if (strtolower($faction['owner']) == strtolower($owner)) {
                foreach ($this->getFactionMembers($name) as $key => $member) {
                    //if($key != $owner) {
                    $this->quit($key, $name);
                    //}
                }
                unset($this->faction[$name]);
                unset($this->member[$faction]);
                return true;
            }
        }
        return false;
    }

    public function join($name, $faction)
    {
        $data = $this->getMember($name);
        if ($data != null) {
            return false;
        }
        $f = $this->getFaction($faction);
        if ($f == null) {
            return false;
        }
        $array = $this->getFactionMembers($faction);
        if (count($array) >= $this->conf->get('member-limit', 100)) {
            return false;
        }
        $data = ['position' => 'normal', 'level' => 1, 'contribution' => 0];
        $this->member[$faction][strtolower($name)] = $data;
        return true;
    }

    public function quit($name, $faction)
    {
        $f = $this->getFaction($faction);
        if ($f == null or strtolower($f['owner']) == $faction) {
            return false;
        }
        if (array_key_exists($faction, $this->member)) {
            $array = array_change_key_case($this->member[$faction]);
            if (array_key_exists(strtolower($name), $array)) {
                unset($array[strtolower($name)]);
                $this->member[$faction] = $array;
                return true;
            }
        }
        return false;
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args) : bool {
        switch($command->getName()){
            case 'f':
                switch(array_shift($args)){
                    case 'create':
                        if ($sender instanceof ConsoleCommandSender) {
                            $sender->sendMessage("Don't send this command in console");
                            return true;
                        }
                        if(count($args) < 1){
                            return false;
                        }
                        if(array_key_exists($args[0], $this->faction)){
                            $owner = $this->faction[$args[0]]['owner'];
                            $sender->sendMessage('公会: '.$args[0].' 已结存在. 会长: '.$owner);
                            return true;
                        }
                        $data = $this->getMember($sender->getName());
                        if($data != null){
                            $sender->sendMessage("你已经加入了公会'".$data['faction']."', 请先退出当前公会.");
                            return true;
                        }
                        $result = $this->creatorFaction($args[0], $sender->getName());
                        if($result){
                            $sender->sendMessage('创建成功,公会名称: '.$args[0]);
                        }else{
                            $sender->sendMessage('创建失败,发生意料之外的错误');
                        }
                        return true;
                    case 'disband':
                        return true;
                    case 'list':
                        $array = array_keys($this->getFactions());
                        $sender->sendMessage('-----Faction List------');
                        foreach($array as $name){
                            $sender->sendMessage('- '.$name);
                        }
                        return true;
                    case 'help':
                    default:
                        $sender->sendMessage("-----/f help-----");
                        return true;
                }
                break;
            case 'fcmd':
                switch(array_shift($args)) {
                    case 'chat':
                        return true;
                    case 'help':
                    default:
                        $sender->sendMessage("-----/fcmd help-----");
                        return true;
                }
            case 'fadmin':
                switch(array_shift($args)) {
                    case 'map':
                        switch(array_shift($args)) {
                            case 'map':
                                return true;
                            case 'help':
                            default:
                                return true;
                        }
                    case 'save':
                        $time = microtime();
                        $this->save();
                        $sender->sendMessage('Data saved, used time：'.round(microtime() - $time, 3).'second');
                        return true;
                    case 'help':
                    default:
                        $sender->sendMessage("-----/fadmin help-----");
                        return true;
                }
            case 'fhelp':
            default:
                $sender->sendMessage('----/fhelp-----');
                $m = [
                    'f'=>'- /f [create|disband|list|help]',
                    'fcmd'=>'- /fcmd [chat|]',
                ];
                if(count($args) > 0){
                    $cmd = array_shift($args);
                    if (array_key_exists($cmd, $m)) {
                        if ($sender->hasPermission('HFaction.cmd.' . $cmd)) {
                            $sender->sendMessage($m[$cmd]);
                        } else {
                            $sender->sendMessage('You have no permission');
                        }
                    } else {
                        $sender->sendMessage("The command '/" . $cmd . "' does not exist");
                    }
                } else {
                    foreach ($m as $t) {
                        $sender->sendMessage($t);
                    }
                }
                return true;
        }
    }

    public function save(){
        if($this->isEnabled()){
            $factionConfig = new Config($this->path.'Faction.yml', Config::YAML, []);
            $factionConfig->setAll($this->faction);
            $factionConfig->save($this->conf->get('async-save', false));

            $memberConfig = new Config($this->path.'Member.yml', Config::YAML, []);
            $memberConfig->setAll($this->member);
            $memberConfig->save($this->conf->get('async-save', false));
        }
    }

    /*
    private function test2(){
        for($i = 1; $i <= 100; $i++){
            $this->faction['测试'.$i] = ['name'=>'测试'.$i, 'creator'=>'happy163', 'owner'=>'happy163', 'level'=>0, 'money'=>0];
        }
        foreach($this->member as $faction=>$array){
            $this->member[$faction] = [];
        }
        for($i = 1; $i <= 100000; $i++){
            $this->member['测试'.(int)rand(1, 100)]['成员'.$i] = ['level'=>0, 'contribution'=>rand(0, 100)];
        }
        $this->save();
        echo 'over';
    }*/
}