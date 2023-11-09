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
// sprawdzanie poprawnej ilości rekordów: SELECT COUNT(*) FROM api_values WHERE api = '3'
// zapytania nie starsze niż 7 dni
// nie grupować wedlug api
// podac api przed rekordem
echo " <pre>"; // "nie jest jak w matrix"
$xml = new DOMDocument();
$db = mysqli_connect("localhost", "root", "", "test") or die("Unable to connect with the database");
$query = mysqli_query($db, "SELECT id, url FROM urls");
echo "<div id='container'>";
echo "<p>ostatnio dodane rekordy: </p>";
while ($row = mysqli_fetch_assoc($query)) {
    $xmlUrl = $row['url'];
    $xmlId = $row['id'];
//    echo "<table class='table'>";
//    echo "<tr class='row'><td class='cell tableHeader' colspan='2'> url: <u>".$xmlUrl."</u></td></tr>";
    if (is_string($xmlUrl) && !empty($xmlUrl)) {
        $xml = simplexml_load_file($xmlUrl);
        if ($xml) {
            $channel = $xml -> xpath("//channel/item/title");
            $date = $xml -> xpath("//pubDate");
            $channelItems = $xml -> xpath("//channel/item");
            $content = $xml -> xpath("//channel/item/description");
            if($xmlUrl == 'https://developer.ebay.com/rss/api-deprecation'){
                $ebayNotes = $xml -> xpath("//channel/item/notes");
            }
            $preparedTitle = array();
            $preparedDescription = array();
            $md5 = array();
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
                        $updateDate = 1;
                        // przygotowywuje kwerende do wykonania
                        $statement  = $db->prepare("INSERT INTO `api_values` (`title`, `description`, `date_addition`, `md5`, `date_update`, `api`) VALUES (?, ?, ?, ?, ?, ?)");
                        // ustawia wartosci zmiennych na s - string oraz i - int zabezpiecza przed dodaniem wartosci o niepoprawnych typie danych
                        $statement->bind_param("ssssii", $preparedTitle[$titleIndex], $descriptionWithSlashes, $preparedDate, $md5[$titleIndex], $updateDate, $xmlId);
                        // jezeli nie uda sie dodac danych do bazy wypisanie erroru i nie dodawanie błędnych danych
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
$mq = mysqli_query($db, "SELECT title, description, url FROM api_values INNER JOIN urls ON api_values.api = urls.id WHERE DAY(edition.update) > (DAY(CURRENT_TIMESTAMP()) - 7)");
echo "</div>";
$mq = mysqli_query($db, "UPDATE edition SET date_update = CURRENT_TIMESTAMP() WHERE idu = 1");

mysqli_close($db);
echo "</pre>";
//                        echo "<tr>
//                            <td class='cell title'>".$preparedTitle[$titleIndex]."</td>
//                            <td class='cell description'>".str_replace('\\', '', filter_var($descriptionWithSlashes, FILTER_SANITIZE_STRING))."</td>
//                            </tr>";
?>
</body>
</html>
