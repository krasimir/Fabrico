<?php

    class FabricoPackageManager {

        private $gitEndPoint = "https://api.github.com/";
        private $gitEndPointRaw = "https://raw.github.com/";
        private $gitRepos;

        private $packageFileName = "package.json";
        private $modulesDir = "modules";

        public function __construct() {
            global $APP_ROOT;
            $this->gitRepos = (object) array();
            $this->log("fabrico package manager started", "CYAN");
            $this->installModules($APP_ROOT.$this->packageFileName);
        }
        private function installModules($packageFile) {
            global $APP_ROOT;
            if(file_exists($packageFile)) {
                $this->log("Installing modules described in ".str_replace($APP_ROOT, "", $packageFile));
                $sets = json_decode(file_get_contents($packageFile));
                foreach($sets as $set) {                    
                    if($this->shouldContain($set, array("owner", "repository", "modules", "branch")) && $this->shouldBeNonEmptyArray($set->modules)) {
                        $this->log("/".$set->owner."/".$set->repository, "", 1);
                        if(!file_exists($APP_ROOT.$this->modulesDir)) {
                            mkdir($APP_ROOT.$this->modulesDir, 0777);
                        }
                        foreach($set->modules as $module) {
                            $this->installModule($module, $set, $APP_ROOT.$this->modulesDir);
                        }
                    }
                }
            } else {
                $this->warning("Directory '".dirname($packageFile)."' doesn't contain ".$this->packageFileName." file.");
            }
        }
        private function installModule($module, $set, $installInDir) {
            global $APP_ROOT;
            if($this->shouldContain($module, array("path"))) {
                $this->formatModule($module);
                if(!file_exists($installInDir."/".$module->name)) {
                    mkdir($installInDir."/".$module->name, 0777);
                }
                $tree = $this->readRepository($set);
                $found = false;
                if(isset($tree->tree)) {
                    foreach($tree->tree as $item) {
                        if(strpos($item->path, $module->path) === 0 && $item->path !== $module->path) {
                            $found = true;
                            if($item->type == "blob") {
                                $content = $this->request($this->gitEndPointRaw.$set->owner."/".$set->repository."/".$tree->sha."/".$item->path, false);
                                $path = str_replace($module->path."/", "", $item->path);
                                $fileToBeSaved = $installInDir."/".$module->name."/".$path;
                                if(file_put_contents($fileToBeSaved, $content) !== false) {
                                    $this->log($item->path." file added", "", 2);
                                } else {
                                    $this->error($item->path." file is not added", "", 2);
                                }
                            } else if($item->type == "tree") {
                                $path = str_replace($module->path."/", "", $item->path);
                                $directoryToBeCreated = $installInDir."/".$module->name."/".$path;
                                if(!file_exists($directoryToBeCreated)) {
                                    if(mkdir($directoryToBeCreated, 0777)) {
                                        $this->log($path." directory created", "", 2);
                                    } else {
                                        $this->error($path." directory is no created", "", 2);
                                    }
                                }
                            }
                        }
                    }
                }
                if(!$found) {
                    $this->error("'".$module->path."' was not found in repository '".$set->owner."/".$set->repository."' (branch: '".$set->branch."')", 2);
                    rmdir($installInDir."/".$module->name);
                } else {
                    if(isset($tree->sha)) {
                        $fileToBeSaved = $installInDir."/".$module->name."/commit.sha";
                        if(file_put_contents($fileToBeSaved, $tree->sha) !== false) {
                            $this->log($module->name."/commit.sha file added (".$tree->sha.")", "", 2);
                        } else {
                            $this->error($module->name."/commit.sha file is node added", "", 2);
                        }
                    }
                }
            }
        }
        private function readRepository(&$set) {
            $repoPath = $set->owner."/".$set->repository."/branches/".$set->branch;
            if(isset($this->gitRepos->{$repoPath})) {
                return $this->gitRepos->{$repoPath};
            }
            if(!isset($set->commit)) {
                $masterBranchURL = $this->gitEndPoint."repos/".$set->owner."/".$set->repository."/branches/".$set->branch;
                $masterBranch = $this->request($masterBranchURL);
                $set->commit = $masterBranch->commit->sha;
            }
            $treeURL = $this->gitEndPoint."repos/".$set->owner."/".$set->repository."/git/trees/".$set->commit."?recursive=1";
            $tree = $this->request($treeURL);
            $this->gitRepos->{$repoPath} = $tree;
            return $tree;
        }

        // formatting
        private function formatModule(&$module) {
            $module->path = substr($module->path, strlen($module->path)-1, 1) == "/" ? substr($module->path, 0, strlen($module->path)-1) : $module->path;
            $pathParts = explode("/", $module->path);
            $module->name = $pathParts[count($pathParts)-1];
        }

        // requesting
        private function request($url, $json = true) {
            $ch = curl_init();
            curl_setopt($ch,CURLOPT_URL,$url);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,1); 
            curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,0);
            $content = curl_exec($ch);
            curl_close($ch);
            if($json) {
                return json_decode($content);
            } else {
                return $content;
            }
        }

        // validation
        private function shouldContain($ob, $properties, $message = "Missing property '{prop}'!") {
            foreach($properties as $prop) {
                if(!isset($ob->{$prop})) {
                    $this->error(str_replace("{prop}", $prop, $message));
                    return false;
                }
            }
            return true;
        }
        private function shouldBeNonEmptyArray($arr) {
            return is_array($arr) && count($arr) > 0;
        }

        // output
        private function error($str, $indent = 0) {
            $this->log("Error: ".$str, "RED", $indent);
        }
        private function warning($str) {
            $this->log("Warning: ".$str);
        }
        private function log($str, $color = "", $indent = 0) {
            $colors = array(
                "BLACK" => "\033[00;30m",
                "RED" => "\033[00;31m",
                "GREEN" => "\033[00;32m",
                "YELLOW" => "\033[00;33m",
                "BLUE" => "\033[00;34m",
                "MAGENTA" => "\033[00;35m",
                "CYAN" => "\033[00;36m",
                "WHITE" => "\033[00;37m",
                "" => ""
            );
            $indentStr = "";
            for($i=0; $i<$indent; $i++) {
                $indentStr .= "   ";
            }
            echo $colors[$color].$indentStr."> ".$str."\033[39m\n";
        }

    }

?>