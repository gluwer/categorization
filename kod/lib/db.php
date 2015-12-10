<?

/**
 * Klasa z statycznymi metodami pomocnymi przy korzystaniu z bazy danych.
 * U³atwia pobieranie pojedynczej warto¶ci, pojedynczego wiersza, wielu wierszy,
 * i wyniku zapytania, w którym pierwsza kolumna to klucz.
 *
 */
class DBHelper {

  /**
   * Funkcja tworzy po³±czenie z baz± danych.
   *
   * @param string $serv
   * @param string $user
   * @param string $pass
   * @param string $db
   * @return mysqli
   * @exception Exception Je¶li wyst±pi³ b³±d po³±czenia z BD.
   */
  static public function connect($serv, $user, $pass, $db) {
    $mysqli = new mysqli($serv, $user, $pass, $db);
    if (mysqli_connect_errno()) {
      throw new Exception(mysqli_connect_error());
    }
    if (!$mysqli->set_charset("latin2")) {
      throw new Exception($mysqli->error);
    }
    return $mysqli;
  }

  /**
   * Umo¿liwia szybkie pobranie jednej warto¶ci z bazy danych.
   * Zapewnia wykonanie zapytania i zwolnienie zasobów wyniku.
   *
   * @param mysqli $mysqli
   * @param string $query
   * @return mixed
   * @exception Exception Je¶li nie uzyskano zbioru wyników.
   */
  static public function getOne(mysqli $mysqli, $query) {
    $res = $mysqli->query($query);
    if ($res===false || $res===true) {
      throw new Exception('Nie uzyskano zbioru danych!');
    }
    $row = $res->fetch_row();
    $res->close();
    if ($row === null) {
      throw new Exception('Pusty wynik!');
    }
    return $row[0];
  }

  /**
   * Umo¿liwia szybkie pobranie jednego wiersza z bazy danych.
   * Zapewnia wykonanie zapytania i zwolnienie zasobów wyniku.
   *
   * @param mysqli $mysqli
   * @param string $query
   * @return array
   * @exception Exception Je¶li nie uzyskano zbioru wyników.
   */
  static public function getRow(mysqli $mysqli, $query) {
    $res = $mysqli->query($query);
    if ($res===false || $res===true) {
      throw new Exception('Nie uzyskano zbioru danych!');
    }
    $row = $res->fetch_row();
    $res->close();
    if ($row === null) {
      throw new Exception('Pusty wynik!');
    }
    return $row;
  }

 /**
   * Umo¿liwia szybkie uzyskanie pary klucz i warto¶æ z bazy danych.
   * Zapewnia wykonanie zapytania i zwolnienie zasobów wyniku.
   *
   * @param mysqli $mysqli
   * @param string $query
   * @return array
   * @exception Exception Je¶li nie uzyskano zbioru wyników.
   */
  static public function getAssoc(mysqli $mysqli, $query) {
    $res = $mysqli->query($query);
    if ($res===false || $res===true) {
      throw new Exception('Nie uzyskano zbioru danych!');
    }
    if ($res->field_count < 2) {
      throw new Exception('Mniej ni¿ dwie kolumny!');
    }
    $results = array();
    if ($res->field_count > 2) {
      while (is_array($row = $res->fetch_row())) {
        $key = array_shift($row);
        $results[$key] = $row;
      }
    } elseif ($res->field_count == 2) {
      while (is_array($row = $res->fetch_row())) {
        $results[$row[0]] = $row[1];
      }
    } else {
      while (is_array($row = $res->fetch_row())) {
        $results[] = $row[0];
      }
    }
    $res->close();
    return $results;
  }

  /**
   * Umo¿liwia wygodne pobranie wielu wierszy z bazy danych.
   * Zapewnia wykonanie zapytania i zwolnienie zasobów wyniku.
   *
   * @param mysqli $mysqli
   * @param string $query
   * @return array
   * @exception Exception Je¶li nie uzyskano zbioru wyników.
   */
  static public function getAll(mysqli $mysqli, $query) {
    $res = $mysqli->query($query,MYSQLI_USE_RESULT);
    if ($res===false || $res===true) {
      throw new Exception('Nie uzyskano zbioru danych!');
    }
    $results = array();
    while ($row = $res->fetch_row()) {
      $results[] = $row;
    }
    $res->close();
    return $results;
  }

}
?>