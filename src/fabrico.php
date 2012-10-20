<?php

    class FabricoPackageManager {

        private $gitEndPoint = "https://api.github.com/";
        private $gitEndPointRaw = "https://raw.github.com/";
        private $gitRepos;
        private $installedModules;

        private $packageFileName = "package.json";
        private $modulesDir = "modules";

        public function __construct() {
            global $APP_ROOT;
            if(!isset($APP_ROOT)) {
                $this->error("\$APP_ROOT is not defined. Please add '\$APP_ROOT = dirname(__FILE__).\"/\";' to your php file.");
                return;
            }
            $this->log("Fabrico Package Manager started", "GREEN");
            $this->gitRepos = (object) array();
            $this->installedModules = (object) array();
            $this->installModules($APP_ROOT.$this->packageFileName);
            $this->reportResults();
        }
        private function installModules($packageFile, $indent = 0) {
            global $APP_ROOT;
            if(file_exists($packageFile)) {
                $this->log("Working in: ".dirname($packageFile), "GREEN");
                $sets = json_decode(file_get_contents($packageFile));
                if(!$this->validateSets($sets, $packageFile)) {
                    return;
                }
                foreach($sets as $set) {
                    if($this->shouldContain($set, array("owner", "repository", "branch"))) {
                        $this->log("repository: /".$set->owner."/".$set->repository, "BLUE", $indent);
                        $dir = dirname($packageFile)."/".$this->modulesDir;
                        if(!file_exists($dir)) {
                            mkdir($dir, 0777);
                        }
                        $this->formatModules($set);
                        foreach($set->modules as $module) {
                            $this->installModule($module, $set, $dir, $indent);
                        }
                    }
                }
            } else {
                $this->warning("Directory '".dirname($packageFile)."' doesn't contain ".$this->packageFileName." file.", "", $indent);
            }
        }
        private function installModule($module, $set, $installInDir, $indent) {
            global $APP_ROOT;
            $tree = $this->readRepository($set);
            $found = false;
            if(isset($this->installedModules->{$set->owner."/".$set->repository."/".$module->path})) {
                if($this->installedModules->{$set->owner."/".$set->repository."/".$module->path}->sha === $tree->sha) {
                    $this->log($module->name." module skipped", "BLUE", $indent + 1);
                    return;
                }
            }
            if(file_exists($installInDir."/".$module->name)) {
                $this->rmdir_recursive($installInDir."/".$module->name);
            }
            if(mkdir($installInDir."/".$module->name, 0777)) {
                $this->log("/".$module->name, "", $indent + 1);
            } else {
                $this->error("/".$module->name." directory is no created", "", $indent + 1);
            }
            if(isset($tree->tree)) {
                foreach($tree->tree as $item) {
                    if(($module->path == "" || $module->path == "/" || strpos($item->path, $module->path) === 0) && $item->path !== $module->path) {
                        $found = true;
                        if($item->type == "blob") {
                            $content = $this->request($this->gitEndPointRaw.$set->owner."/".$set->repository."/".$tree->sha."/".$item->path, false);
                            $path = $module->path != "" ? str_replace($module->path."/", "", $item->path) : $item->path;
                            $fileToBeSaved = $installInDir."/".$module->name."/".$path;
                            if(file_put_contents($fileToBeSaved, $content) !== false) {
                                $this->log("/".$item->path, "", $indent + 2);
                            } else {
                                $this->error("/".$item->path." file is not added", "", $indent + 2);
                            }
                        } else if($item->type == "tree") {
                            $path = $module->path != "" ? str_replace($module->path."/", "", $item->path) : $item->path;
                            $directoryToBeCreated = $installInDir."/".$module->name."/".$path;
                            if(!file_exists($directoryToBeCreated)) {
                                if(mkdir($directoryToBeCreated, 0777)) {
                                    $this->log("/".$path, "", $indent + 1);
                                } else {
                                    $this->error("/".$path." directory is no created", "", $indent + 1);
                                }
                            }
                        }
                    }
                }
            }
            if(!$found) {
                $this->error("'".$module->path."' was not found in repository '".$set->owner."/".$set->repository."' (branch: '".$set->branch."')", $indent + 1);
                rmdir($installInDir."/".$module->name);
            } else {
                if(isset($tree->sha)) {
                    $fileToBeSaved = $installInDir."/".$module->name."/commit.sha";
                    if(file_put_contents($fileToBeSaved, $tree->sha) !== false) {
                        $this->log("/".$module->name."/commit.sha sha=".$tree->sha."", "", $indent + 2);
                    } else {
                        $this->error("/".$module->name."/commit.sha file is node added", "", $indent + 2);
                    }
                    $this->installedModules->{$set->owner."/".$set->repository."/".$module->path} = (object) array("sha" => $tree->sha);
                    if(file_exists($installInDir."/".$module->name."/package.json")) {
                        $this->installModules($installInDir."/".$module->name."/package.json", $indent + 1);
                    }
                }
            }
        }
        private function readRepository(&$set) {
            $repoPath = $set->owner."/".$set->repository."/branches/".$set->branch;
            if(isset($this->gitRepos->{$repoPath})) {
                if(isset($set->commit) && $set->commit == $this->gitRepos->{$repoPath}->sha) {
                    return $this->gitRepos->{$repoPath};
                }
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
        private function formatModules(&$set) {
            if(!isset($set->modules)) {
                $set->modules = array((object) array());
            }
            foreach($set->modules as $module) {
                if(!isset($module->path)) {
                    $module->path = "";
                }
                if(!isset($module->name)) {
                    if($module->path === "") {
                        $module->name = $set->repository;
                    } else {
                        $pathParts = explode("/", $module->path);
                        $module->name = $pathParts[count($pathParts)-1];
                    }
                }
                $module->path = substr($module->path, strlen($module->path)-1, 1) == "/" ? substr($module->path, 0, strlen($module->path)-1) : $module->path;
                $module->path = substr($module->path, 0, 1) == "/" ? substr($module->path, 1, strlen($module->path)) : $module->path;
            }
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
            echo $colors[$color].$indentStr.$str."\033[39m\n";
        }
        private function validateSets($sets, $packageFile) {
            if(gettype($sets) == "array") {
                if(count($sets) === 0) {
                    $this->error($packageFile." has not defined modules.");
                    return false;
                }
            } else {
                $this->error($packageFile." has not a valid format.");
                return false;
            }
            return true;
        }

        // reporting
        private function reportResults() {
            $this->log("Installed Modules", "GREEN");
            foreach($this->installedModules as $key => $value) {
                $this->log($key, "GREEN", 1);
            }
        }

        // removing directory and its content
        private function rmdir_recursive($dir) {
            $files = scandir($dir);
            array_shift($files);    // remove '.' from array
            array_shift($files);    // remove '..' from array
           
            foreach ($files as $file) {
                $file = $dir . '/' . $file;
                if (is_dir($file)) {
                    $this->rmdir_recursive($file);
                    @rmdir($file);
                } else {
                    @unlink($file);
                }
            }
            @rmdir($dir);
        }

    }

    // running the package manager
    if(php_sapi_name() === "cli") {
        $manager = new FabricoPackageManager();
    }

?>