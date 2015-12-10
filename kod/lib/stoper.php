<?
/**
 * Klasa Timer
 * Mierzy czasy wykonania skryptu
 * Start i wykonanie pierwszego pomiaru (nazwa "start"):
 *        $t = new Timer();
 * Pomiar:
 *        $t->set(nazwa);
 * Pobiera pomiar o konkretnej warto¶ci:
 *        $t->get(nazwa);
 * Koñczy wyliczenia, zwraca tablicê wszystkich czasów:
 *        $t->endAndList();
 *
 * Format tablicy: ["nazwa"][0-czas od poprzedniej nazwy, 1-czas od startu]
*/
class Timer{

  /**
   * Przechowuje zebrane czasy.
   *
   * @var array
   */
  private $time = array();

  /**
   * Przechowuje czas od poprzedniego startu.
   *
   * @var float
   */
  private $start_time = 0.0;

  /**
   * Tworzy i inicjalizuje zbieranie czasów, dodaje czas start.
   *
   */
  public function __construct() {
    $t = explode(" ",microtime());
    $this->start_time = ($t[0]+$t[1]);
    $this->time["start"] = $this->start_time;
  }

  /**
   * Zapamiêtuje czas pod podan± nazw± i dodatkowo go zwraca.
   *
   * @param string $name
   * @return float
   */
  public function set($name) {
    $t = explode(" ",microtime());
    $tt = ($t[0]+$t[1]);
    if (!isset($this->time[$name])) $this->time[$name] = 0.0;
    $this->time[$name] += $tt - $this->start_time;
    $this->start_time = $tt;
    return $this->time[$name];
  }

  /**
   * Zwraca czas od pocz±tku utworzenia obiektu lub czas wzglêdem innego punktu,
   * o ile zosta³ podany jako drugi argument.
   *
   * @param string $name
   * @return float
   */
  public function get($name) {
    return $this->time[$name];
  }

  /**
   * Zwraca tablicê informuj±c± o wynikach pomiarów.
   *
   * @param bool[optional] $format Zapewnia formatowanie wyników.
   * @return array
   */
  public function endAndList($format = false) {
    $tend = explode(" ",microtime());
    $start = $this->time['start'];
    unset($this->time['start']);
    $end = ($tend[0]+$tend[1]);
    $t = array();
    foreach ($this->time as $k => $v) {
    	$t[$k] = ($format)?$this->format($v):$v;
    }
    $t["all"] = ($format)?$this->format($end - $start):$end - $start;
    $this->time['start'] = $start;
    return $t;
  }

  /**
   * Zapewnia odpowiednie formatowanie czasu z float na minuty,
   * sekundy i milisekundy.
   *
   * @static
   * @return string
   * @param float $t
   */
  static function format($t) {
    $txt = "";
    if ($t>=60) {
      $tt = floor($t/60);
      if ($tt<10) $txt .= "0";
      $txt .= $tt."m ";
      $t -= $tt * 60;
    }
    if ($txt) {
      if ($t<10) $txt .= "0";
    }
    $txt .= round($t,4).'s ';
    return $txt;
  }
}

?>