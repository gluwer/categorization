<?
/**
 * Klasa zapewniaj±ca metody sprawdzaj±ce, czy tekst poddawany
 * analizie zawiera adresy WWW lub e-mail.
 *
 * Dodatkowo po utworzeniu egzemplarza tej klasy i podaniu
 * pliku z formami s³ownikowymi wulgaryzmów, zapewnia metodê informuj±c±
 * o liczbie odnalezionych wulgaryzmów.
 *
 */
class Validation {

  /**
   * Enter description here...
   *
   * @var unknown_type
   */
  private $vulg = array();

  /**
   * Poszukuje w przekazanym tek¶cie adresu e-mail w postaci niekoniecznie
   * w pe³ni poprawnej (obs³uguje wyró¿nienie @, (at) i ' at ' (bez ').
   *
   * @param string $text Pe³ny tekst do analizy.
   * @return bool True, je¶li instnieje. False w przeciwnym razie.
   */
  static function findEmail($text) {
    if (preg_match('/\w[-._\w]*\w.?(@|[( ]at[) ])\w[-._\w]*\w\.\w{2,4}/',$text)) {
      return true;
    }
    return false;
  }

  /**
   * Poszukuje w przekazanym tek¶cie adresu WWW w postaci niekoniecznie
   * w pe³ni poprawnej. W ten sposób wychwytuje niektóre sposoby
   * przemycania adresu URL, ale mo¿e równie¿ wzbudziæ fa³szywy alarm.
   *
   * @param string $text Pe³ny tekst do analizy.
   * @return bool True, je¶li instnieje. False w przeciwnym razie.
   */
  static function findWWW($text) {
    if (preg_match('/[a-zA-Z][\w]*([\.+][\w]+\*)*([\.+\*](pl|com|org|net|edu|info|biz|fm|eu|de|fr|uk))(\W|$)/',$text)) {
      return true;
    }
    return false;
  }

  function __construct($file) {
    $handle = @fopen($file, "r");
    if ($handle) {
      while (!feof($handle)) {
        $this->vulg[] = substr(fgets($handle, 50),0,-1);
      }
      fclose($handle);
    }
  }

  /**
   * Poszukuje wyrazów wulgarnych w przetworzonej tablicy
   *
   * @param array $words Tablica s³ów s³ownikowych (ma³e litery).
   * @return bool True, je¶li instnieje. False w przeciwnym razie.
   */
  public function findVulgarism($words) {
    foreach ($words as $w) {
    	if (in_array($w, $this->vulg, true)) return true;
    }
    return false;
  }
}

/*include('../fsa/fsav-opt.php');
include('stoper.php');
$v = new Validation('../dict/vulgarism.txt');
$f = new Fsav('../dict/vulg.fsa');
$ar = array('test','potrawa','tuczek','temat','turbulencja','transformacja','toga','agrafka','chuj', 'torba', 'zajebisty', 'asercja', 'biurokracja');
$stoper = new Timer();
$t1 = $v->findVulgarism($ar);
var_dump($t1);
$stoper->set('after1');
$t2 = false;
foreach ($ar as $w) {
 	if ($f->find_word($w)) $t2 = true;
}
$stoper->set('after2');
echo '<pre>';
print_r($stoper->endAndList());
echo '</pre>';*/
?>