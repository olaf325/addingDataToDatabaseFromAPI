# addingDataToDatabaseFromAPI
# Przeznaczenie
Skrypt pobierający feed changeLog'ów w postaci RSS, z bazy danych. Wypisuje jedynie zmiany, które zaszły w ostatnim tygodniu.
## analiza kodu
pobieranie z bazy danych url xml, z którego pobierane są za pomocą xpath wartości: tytuł, data wprowadzenia zmiany, zawartość całego rekordu oraz opis.
Żeby zapobiec kilkakrotnemu dodawaniu tych samych danych do bazy, z całego rekordu tworzone jest md5 i sprawdza się, czy takowe znajduje się juz w tabeli.
```php
$md5[] = md5($item->asXML());
foreach ($channel as $titleIndex => $titleValue) {
    $processed = mysqli_query($db, "SELECT COUNT(*) FROM api_values WHERE md5 = '$md5[$titleIndex]'");
    $count = mysqli_fetch_row($processed)[0];
    if($count == 0){
```
jeżeli w tabeli nie występuje taki rekord, kod przystępuje do przygotowywania danych do wprowadzenia.
Zostaje przygotowany tytuł, data (przyjmuje format dateTime) oraz opis.
``` php
$preparedDate = $dateTime->format("Y-m-d H:i:s");
```
Jeżeli któryś z rekordów nie posiada podanego czasu, przyjmuje wtedy czas wprowadzenia do bazy.
Gdy wszystkie wartości są przygotowane, zostają one wprowadzone do bazy danych.
Wcześniej jednak następuje zabezpieczenie przed wprowadzeniem cudzego kodu do bazy.
```php
if (isset($preparedDate)) {
   $descriptionWithSlashes = addcslashes($preparedDescription[$titleIndex], '<>/');
   statement  = $db->prepare("INSERT INTO `api_values` (`title`, `description`, `date_addition`, `md5`, `api`) VALUES (?, ?, ?, ?, ?, ?)");
   $statement->bind_param("ssssi", $preparedTitle[$titleIndex], $descriptionWithSlashes, $preparedDate, $md5[$titleIndex],  $xmlId);
   if (!$statement->execute()) {
      echo "Error inserting data: " . $statement->error;
   }
   $statement->close();
}
```
Gdy wszystkie dane są wprowadzone, skrypt wypisuje te, które zostały dodane w ostatnim tygodniu
```php
$mq = mysqli_query($db, "SELECT `api_values`.title, description, `urls`.api_title, `api_values`.md5 FROM `api_values` INNER JOIN `urls` ON `api_values`.api = `urls`.id WHERE `api_values`.date_addition between date_sub(now(),INTERVAL 1 WEEK) and now()");
```
