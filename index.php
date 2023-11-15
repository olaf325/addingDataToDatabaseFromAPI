<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="./styl.css">
    <title>urls</title>
</head>
<body>
<?php
echo " <pre>";
$xml = new DOMDocument();
$db = mysqli_connect("localhost", "root", "", "test") or die("Unable to connect with the database");
$query = mysqli_query($db, "SELECT id, url FROM urls");
echo "<div id='container'>";
echo "<p>ostatnio dodane rekordy: </p>";
while ($row = mysqli_fetch_assoc($query)) {
    $xmlUrl = $row['url'];
    $xmlId = $row['id'];
    if (is_string($xmlUrl) && !empty($xmlUrl)) {
        $xml = simplexml_load_file($xmlUrl);
        if ($xml) {
            $preparedDescription = array();
            $preparedTitle = array();
            $md5 = array();
            $ebayNotes = array();
            $channel = $xml -> xpath("//channel/item/title");
            $date = $xml -> xpath("//pubDate");
            $channelItems = $xml -> xpath("//channel/item");
            $content = $xml -> xpath("//channel/item/description");
            if($xmlUrl == 'https://developer.ebay.com/rss/api-deprecation'){
                $ebayNotes = $xml -> xpath("//channel/item/notes");
            }
            foreach ($channelItems as $item) {
                $md5[] = md5($item->asXML());
            }
            foreach ($channel as $titleIndex => $titleValue) {
                $processed = mysqli_query($db, "SELECT COUNT(*) FROM api_values WHERE md5 = '$md5[$titleIndex]'");
                $count = mysqli_fetch_row($processed)[0];
                if($count == 0){
                    foreach ($content as $contentIndex => $contentValue) {
                        $description = (string)$contentValue;
                        if(!empty($ebayNotes)){
                            foreach ($ebayNotes as $notesIndex => $notesValue) {
                                $note = (string)$notesValue;
                                $preparedDescription[$notesIndex] = $note." ".$description;
                            }
                        }else {
                            $preparedDescription[$contentIndex] = $description;
                        }
                    }
                    $title = (string)$titleValue;
                    $preparedTitle[$titleIndex] = $title;
                    if (!empty($date)){
                        $pubDate = (string)$date[$titleIndex];
                        $dateTime = DateTime::createFromFormat("D, d M Y H:i:s e", "$pubDate");
                        if ($dateTime === false) {
                            $preparedDate = date('Y-m-d H:i:s', time());
                        } else {
                            $preparedDate = $dateTime->format("Y-m-d H:i:s");
                        }
                    } else {
                        $preparedDate = date('Y-m-d H:i:s', time());
                    }
                    if (isset($preparedDate)) {
                        $descriptionWithSlashes = addcslashes($preparedDescription[$titleIndex], '<>/');
                        $statement  = $db->prepare("INSERT INTO `api_values` (`title`, `description`, `date_addition`, `md5`, `api`) VALUES (?, ?, ?, ?, ?, ?)");
                        $statement->bind_param("ssssi", $preparedTitle[$titleIndex], $descriptionWithSlashes, $preparedDate, $md5[$titleIndex],  $xmlId);
                        if (!$statement->execute()) {
                            echo "Error inserting data: " . $statement->error;
                        }
                        $statement->close();
                    }
                }
            }
        } else echo "Unable to connect with the file.<br>";
        echo"</table>";
    } else echo "the file is empty or is not a string.<br> ";
}
$mq = mysqli_query($db, "SELECT `api_values`.title, description, `urls`.api_title, `api_values`.md5 FROM `api_values` INNER JOIN `urls` ON `api_values`.api = `urls`.id WHERE `api_values`.date_addition between date_sub(now(),INTERVAL 1 WEEK) and now()");

echo "<table class='table'>";
while($row = mysqli_fetch_assoc($mq)){
    $title = $row['title'];
    $description = $row['description'];
    $url = $row['api_title'];
    echo "<tr>
        <td class='cell tableHeader'>".$url."</td>
        <td class='cell title' ><p>".$title."</p></td>
        <td class='cell description'><p>".str_replace('\\', '', filter_var($description, FILTER_SANITIZE_STRING))."</p></td>
        </tr>";
}
echo "</table>";
echo "</div>";

mysqli_close($db);
echo "</pre>";
?>
</body>
</html>
