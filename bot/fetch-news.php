#!/usr/bin/php
<?php

date_default_timezone_set("UTC");

$red = "daily-mail,fox-news,breitbart-news,the-economist,rt,the-wall-street-journal";
$blue = "the-hill,the-huffington-post,bbc-news,the-guardian-uk,cnn,the-new-york-times,the-washington-post,politico,bloomberg,associated-press,reuters";

$query = ["apiKey" => "YOUR_NEWS_API_KEY_HERE", "language" => "en", "sources" => $red . "," . $blue];
$url = "https://newsapi.org/v2/top-headlines";

$url .= "?" . http_build_query($query);

try{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HEADER, 0);  
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-No-Cache: true'));
    $output = curl_exec($ch);
    $json = json_decode($output);
    curl_close($ch);
} catch (Exception $e) { echo "Bad CURL" . PHP_EOL; exit(); }
if(!isset($json->articles)){ echo "Bad JSON" . PHP_EOL; exit(); }
if(count($json->articles) < 50){ echo "Few Articles" . PHP_EOL; exit(); }

echo "Successfuly fetched news." . PHP_EOL;

$path = __DIR__ . "/../public_html/news.json";
$file = fopen($path, "a+");
if (flock($file, LOCK_EX)) {

    $articles = [];
    $articles_no_date = [];

    for($i = 0; $i < count($json->articles); $i++){
        $data["source"] = $json->articles[$i]->source->name;
        $data["title"] = $json->articles[$i]->title;
        $data["published"] = $json->articles[$i]->publishedAt;
        $data["img"] = $json->articles[$i]->urlToImage;
        $data["url"] = $json->articles[$i]->url;
        $data["bias"] = "error";

        if(strpos($red, $json->articles[$i]->source->id) !== false) $data["bias"] = "red";
        if(strpos($blue,$json->articles[$i]->source->id) !== false) $data["bias"] = "blue";

        if($data["source"] === "The Guardian (UK)") $data["source"] = "The Guardian";
        if($data["source"] === "Al Jazeera English") $data["source"] = "Al Jazeera";

        if($data["img"] == null) continue;

        // add https for urls without protocol
        if( substr($data["img"], 0, 2) === "//" ) {
            $data["img"] = "https:" . $data["img"];
        }

        // check image http response is 200
        try{
            $ch = curl_init($data["img"]);
            curl_setopt($ch, CURLOPT_HEADER, true);  
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
            curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, true);        
            curl_exec($ch);
            $response = curl_getinfo($ch, CURLINFO_HTTP_CODE) . PHP_EOL;
            curl_close($ch);
            if(trim($response) !== "200") { continue; }
        } catch (Exception $e) { continue; }

        if($data["published"] == null){
            array_push($articles_no_date, $data);
            continue;
        } 

        if($data["published"] != null){
            if( (time() - strtotime($data["published"]))/(3600*24) > 2 ){ // 2 days, too old
                continue;
            } else {
                $data["published"] = strtotime($data["published"]);
                if( time() < $data["published"] ){                     
                    $data["published"] = time() - rand(60,600); // no publish date in future
                }
            }
        }
        array_push($articles, $data);
    }

    usort($articles, function($a, $b) { return $a["published"] > $b["published"] ? -1 : 1; });

    // sprinkle in articles with no date randomly
    for($i = 0; $i < count($articles_no_date);$i++) {
        $j = rand(0,count($articles)-1);
        array_splice($articles,$j,0,[$articles_no_date[$i]]);
    }
    
    file_put_contents($path, json_encode($articles));
    flock($file, LOCK_UN);
    echo "Successfuly wrote news to json." . PHP_EOL;
}
fclose($file);


?>
