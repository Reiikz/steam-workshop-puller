<?php

// test for cache info files
$meta = "";
if(!file_exists("cache")){
    mkdir("cache");
}
if(file_exists("cache/meta.yaml")){
    $meta = yaml_parse_file("cache/meta.yaml");
}else{
    $meta = array(
        "lastUpdate" => 0,
    );
    yaml_emit_file("cache/meta.yaml", $meta);
}


//test if cache need update and laod up the apps list
//here I just handle some caching stuff to ensure people don't flood steam's server with unnecessary requests
//and yes using yaml was tottally necesary 'cause yaml is an awesome thing and if your program doesn't use it
//you don't know how to make programs :P
$apps = "";
if(time() - $meta["lastUpdate"] > 7200){
    $meta["lastUpdate"] = time();
    yaml_emit_file("cache/meta.yaml", $meta);
    
    //update the cache
    $apps_ = file_get_contents("https://api.steampowered.com/ISteamApps/GetAppList/v2/");
    file_put_contents("cache/cached.json", $apps_);
    $apps = json_decode($apps_, true);
    echo "updated cache\n";
}else{
    $apps = json_decode(file_get_contents("cache/cached.json"), true);
    echo "loaded apps from cache\n";
}

$matching = array();
$matchCount = 0;


//regex the hell out of the database
//this is surprisingly fast, I love PHP
foreach($apps["applist"]["apps"] as $app){
    preg_match('/(' . $argv[1] . ')/', $app["name"], $matches, PREG_OFFSET_CAPTURE);
    if(sizeof($matches) > 0){
        $matching[$matchCount] = $app;
        $matchCount++;
    }
}

//vomit out the matching names 'cause verbosity looks awesome
// echo "Found apps matching name", $argv[1], "\n";
// foreach($matching as $app){
//     echo "[" , $app["appid"], "]:  ", $app["name"], "\n";
// }

//stress out the steam server by repeteadly asking it for the workshop of an app so I can evaluate the response
//to figure out which of the apps matching the name input by the user has a workshop entry
//reading steam doc was like, tldr, soooo, this was my first solution
echo "Figuring out which of the matching names have a valid workshop\n";
$candidates = array();
$candidateCount = 0;
$cc = 0;
$c = 0;
foreach($matching as $app){
    $c++;
    $r = "";
    if(file_exists("cache/" . $app["appid"] . ".cachedhtml")){
        $r = file_get_contents("cache/" . $app["appid"] . ".cachedhtml");
    }else{
        $r = file_get_contents("https://steamcommunity.com/app/" . $app["appid"] . "/workshop/");
        file_put_contents("cache/" . $app["appid"] . ".cachedhtml", $r);
    }
    if(strpos($r, 'app\\/workshop') !== false){
        echo "[", $app["appid"], "]:   ", $app["name"], "   \\\\~ yes\n";
        $candidates[$candidateCount] = $app;
        $candidateCount++;
    }else{
        echo "[", $app["appid"], "]:   ", $app["name"], "    \\\\~ no\n";
    }
    if($cc == 20){
        $cc = 0;
        echo "\\\\\\\\  Remeaining: ~", sizeof($matching) - $c, "\n";
        echo "\\\\\\\\  Workshop Enabled Count: ~", sizeof($candidates), "\n";
    }
    $cc++;
}
echo "\n";

///////////////////// Extra stuff
function recurse_copy($src,$dst) { 
    $dir = opendir($src); 
    @mkdir($dst); 
    while(false !== ( $file = readdir($dir)) ) { 
        if (( $file != '.' ) && ( $file != '..' )) { 
            if ( is_dir($src . '/' . $file) ) { 
                recurse_copy($src . '/' . $file,$dst . '/' . $file); 
            } 
            else {
                copy($src . '/' . $file,$dst . '/' . $file);
            } 
        } 
    } 
    closedir($dir); 
} 

// function rmrf($dir){
//     if(is_dir($dir)){
//         $files = scandir($dir);
//         foreach($files as $file){
//             if($file != "." && $file != ".."){
//                 if(is_dir($file)){
//                     rmrf($file);
//                 }else{
//                     unlink($file);
//                 }
//             }
//         }
//     }else{
//         if(file_exists($dir)){
//             unlink($dir);
//         }
//     }
// }

function rmrf($dir) { 
    if (is_dir($dir)) { 
        $objects = scandir($dir); 
        foreach ($objects as $object) { 
        if ($object != "." && $object != "..") { 
            if (filetype($dir."/".$object) == "dir") rmrf($dir."/".$object); else unlink($dir."/".$object); 
        } 
        } 
        reset($objects); 
        rmdir($dir); 
    }else{
        if(file_exists($dir)){
            unlink($dir);
        }
    }
}


///////////////////// Actually perform pulling the mods out

function linux_retrieveSteamLibraries($steampath){
    $steamconfig = file_get_contents("$steampath/config/config.vdf");
    preg_match_all("/BaseInstallFolder_[0-9]+.*/", $steamconfig, $external_libraries);
    foreach($external_libraries[0] as $key => &$library){
        $library = str_replace("\"", "", $library);
        preg_match("/\/.*/", $library, $r);
        $library = $r[0];
    }
    $external_libraries[0][sizeof($external_libraries[0])] = "$steampath";
    return $external_libraries[0];
}

function doTheStuffThisScriptIsSupposedToDo($app){
    $steampath = "";
    if(PHP_OS == "Linux"){
        echo "Linux users beware this program was developed in Debian and may not be able to find your steam installation if running on a different OS, feel free to issue an enhancement on github\n";
        if(strpos(php_uname(), "Debian") !== false){
            echo "Debian, assuming steam at $steampath\n";
            $steampath = str_replace("\n", "", shell_exec('echo ~')) . "/.steam/debian-installation";
        }else{
            echo "Not implemented yet assuming Debian like locations...\n";
            $steampath = str_replace("\n", "", shell_exec('echo ~')) . "/.steam/debian-installation";
        }
        
        $libraries = linux_retrieveSteamLibraries($steampath);
        $cLibrary = 0;
        while( ( $cLibrary < sizeof($libraries) ) && ( ! file_exists($libraries[$cLibrary] . "/steamapps/common/" . $app["name"]) ) ) { $cLibrary++ ; };
        $gameRoot = $libraries[$cLibrary] . "/steamapps/common/" . $app["name"];
        $workshop = $libraries[$cLibrary] . "/steamapps/workshop/content/"  . $app["appid"];
        echo "~~~~~~~~\n";
        echo "Presumibly Found in library: ", $libraries[$cLibrary], "\n";
        echo "Assuming Game Root: ",  $gameRoot, "\n";
        echo "Assuming workshop location: ", $workshop, "\n";

        $mods = scandir($workshop);

        foreach($mods as $mod){
            if($mod == "." || $mod == "..") continue;
            echo "Retrieving info for [$mod]: ";
            $mod_info = "";
            if(file_exists("cache/$mod.modinfo.cachedhtml")){
                $mod_info = file_get_contents("cache/$mod.modinfo.cachedhtml");
            }else{
                $mod_info = file_get_contents("https://steamcommunity.com/sharedfiles/filedetails/?id=$mod");
                file_put_contents("cache/$mod.modinfo.cachedhtml", $mod_info);
            }
            preg_match("/<div class=\"workshopItemTitle\">.*<\/div>/", $mod_info, $title);
            $title = str_replace("<div class=\"workshopItemTitle\">", "", $title[0]);
            $title = str_replace("</div>", "", $title);
            $title = str_replace("/", "|", $title);
            echo "$title << copying into >> ", $app["name"], "/$title\n";

            if(!file_exists($app["name"])){
                mkdir($app["name"]);
            }
            if(file_exists($app["name"] . "/$title")){
                rmrf($app["name"] . "/$title");
            }
            recurse_copy("$workshop/$mod", $app["name"] . "/$title");
            
        }

        echo "Mod retrieaval done, creating reorganized structure (feel free to ctrl + c if you don't need the files outside the folders)\n";
        sleep(5);
        $mods_folders = scandir($app["name"]);
        $ddir = $app["name"] . "_REORGANIZED";
        $dir = $app["name"];
        if(file_exists($ddir)) { rmrf($ddir); }
        mkdir($ddir);
        foreach($mods_folders as $f){
            if($f == "." || $f == "..") continue;
            
            echo "Mod: $f\n";
            $files = scandir("$dir/$f");
            
            foreach($files as $ff){
                if($ff == "." || $ff == "..") continue;
                
                echo "   file: $ff\n";

                if(!is_dir($ff)){
                    preg_match("/\..*$/", $ff, $extension);
                    $extension = $extension[0];
                    $c = 0;
                    if(file_exists("$ddir/$f$extension")){
                        while(file_exists("$ddir/$f-$c$extension")) $c++;
                        copy("$dir/$f/$ff", "$ddir/$f-$c$extension");
                        echo "      dest: $ddir/$f-$c$extension\n";
                    }else{
                        copy("$dir/$f/$ff", "$ddir/$f$extension");
                        echo "      dest: $ddir/$f$extension\n";
                    }
                }else{
                    $c = 0;
                    if(file_exists("$ddir/$f")){
                        while(file_exists("$ddir/$f-$c")) $c++;
                        recurse_copy("$dir/$f/$ff", "$ddir/$f-$c");
                        echo "      dest: $ddir/$f-$c\n";
                    }else{
                        recurse_copy("$dir/$f/$ff", "$ddir/$f");
                        echo "      dest: $ddir/$f\n";
                    }
                }
                echo "\n";
            }
        }
        // $mods_folders = scandir($app["name"]);
        // $ddir = $app["name"] . "_REORGANIZED";
        // $dir = $app["name"];
        // if(!file_exists($ddir)) mkdir($ddir);
        // foreach($mods_folders as $f){
        //     if($f == "." || $f == "..") continue;
        //     if(!is_dir("$dir/$f")){
        //         copy("$dir/$f", "$dir/$f" . "$ddir/$f");
        //     }else{
        //         recurse_copy("$dir/$f/*", "$ddir/");
        //     }
        // }

    }else{
        echo "Assuming windows...";
        echo "Not implemented, exiting";
        exit(1000);
    }
}

///////////////////////////////////////////////////////////


//if my shitty script found valid candidates
if($candidateCount > 0){
    echo "Found $candidateCount apps for download, ";

    //let's ask the user which one if there's more than one
    if($candidateCount > 1){

        echo "select Which of these is the game you want to try to pull workshop content from\n";
        for($i = 0; $i < $candidateCount; $i++){
            echo "[$i]:  ", $candidates[$i]["name"], "\n";
        }

        $in = readline();
        if(is_numeric($in)){
            if($in < 0 || $in >= $candidateCount){
                echo "Hey chief that must be one of the numbers above\n";
                exit(0);
            }else{

                doTheStuffThisScriptIsSupposedToDo($candidates[$in]);
            }
        }else{
            echo "Hey chief that must be one of the numbers above\n";
            exit(0);
        }

    }else{
        echo "proceding to pull mod from workshop's folder\n";
        doTheStuffThisScriptIsSupposedToDo($candidates[0]);
    }
    
}else{
    echo "Unable to find any suitable candidates for workshop download\n";
    exit(0);
}