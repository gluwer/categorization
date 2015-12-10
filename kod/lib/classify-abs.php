<?
require_once(dirname(__FILE__).'/db.php');
require_once(dirname(__FILE__).'/simple_validate.php');
require_once(dirname(__FILE__).'/tokenizer.php');
require_once(dirname(__FILE__).'/../fsa/fsaa-opt.php');
require_once(dirname(__FILE__).'/../fsa/fsal-opt.php');
require_once(dirname(__FILE__).'/../fsa/fsas-opt.php');

abstract class Classify {

  /**
   * Obiekt walidacji.
   *
   * @var Validation
   */
  static protected $validation = null;

  /**
   * Obiekt tokenizera ze stoplist±.
   *
   * @var Tokenizer
   */
  static protected $tokenizer = null;

  /**
   * Obiekt korektora polskich znaków diakrytycznych.
   *
   * @var Fsaa
   */
  static protected $fsaa = null;

  /**
   * Obiekt lematyzera (prosty stemmer).
   *
   * @var Fsal
   */
  static protected $fsal = null;

  /**
   * Uchwyt po³±czenia ze s³ownikiem aspell.
   *
   * @var resource
   */
  static protected $pspell = null;

  /**
   * Obiekt po³±czenia z baz± danych.
   *
   * @var mysqli
   */
  protected $dbconn = null;

  /**
   * Enter description here...
   *
   * @var unknown_type
   */
  protected $idc = null;

  /**
   * Liczba potencjalnych wulgaryzmów (kilka znaków * pod rz±d).
   *
   * @var int
   */
  protected $vulg_prop = 0;

  /**
   * Czy pozostawiaæ nierozpoznane wyrazy?
   *
   * @var bool
   */
  protected $copy_unknown = false;

  /**
   * Konstrukor zapewniaj±cy ogóln± inicjalizacjê systemu przygotowywania danych:
   * tokenizer, korekta ortograficzna, uzupe³nianie polskich znaków, wulgaryzmy.
   *
   * @param mysqli $dbconn Obiekt po³±czenia z baz± danych u¿ywany w podklasach.
   * @param string $dictdir Folder ze s³ownikami, stoplistami itp.
   * @param int $idc Identyfikator wykorzystywanego zestawu komentarzy.
   * @param bool $copy_unknown Czy pozostawiaæ nierozpoznane wyrazy?
   * @param array $options Parametry konkretnego klasyfikatora jako tab. asocjacyjna.
   */
  function __construct($dbconn, $dictdir, $idc, $copy_unknown, $options = null) {
    $this->idc = $idc;
    $this->copy_unknown = $copy_unknown;
    $this->dbconn = $dbconn;
    if (is_null(self::$validation)) {
      self::$validation = new Validation($dictdir.'/vulgarism.txt');
    }
    if (is_null(self::$tokenizer)) {
      self::$tokenizer = new Tokenizer($dictdir.'/stoplist.txt');
    }
    if (is_null(self::$fsaa)) {
      self::$fsaa = new Fsaa($dictdir.'/lort_acc_full.fsa');
    }
    if (is_null(self::$fsal)) {
      self::$fsal = new Fsal($dictdir.'/llems_full.fsa');
    }
    if (is_null(self::$pspell)) {
      $pspell_config = pspell_config_create("pl");
      // opcje zapewniaj±ce wiêksz± szybko¶æ dzia³ania aspell
      pspell_config_ignore($pspell_config, 4);
      pspell_config_mode($pspell_config, PSPELL_FAST);
      pspell_config_runtogether($pspell_config, false);
      self::$pspell = pspell_new_config($pspell_config);
    }
  }

  /**
   * Dokonuje sprawdzenia, czy komentarz zawiera adres WWW lub email.
   * Niekoniecznie musi on byæ ca³kowicie poprawny.
   *
   * @param string $comment Komentarz do analizy.
   * @return bool true, je¶li komentarz jest poprawny
   */
  protected function validateEmailWWW($comment) {
    if (Validation::findEmail($comment) || Validation::findWWW($comment)) return false;
    return true;
  }

  /**
   * Dokonuje tokenizacji i wpisania liczby potencjalnych wulgaryzmów.
   * Zwraca tablicê z wyrazami, o ile jakie¶ znaleziono.
   *
   * @param string $comment Komentarz do przetworzenia.
   * @return array Tablicê wyrazów lub false, je¶li wyrazów nie znaleziono.
   */
  protected function tokenize($comment) {
    $tok_comment = self::$tokenizer->tokenize($comment);
    // zdjêcie informacji o potencjalnych wulgaryzmach
    $this->vulg_prop = intval(array_pop($tok_comment));
    if (count($tok_comment) == 0) return false;
    return $tok_comment;
  }

  /**
   * Dokonuje korekty ortograficznej.
   * Je¶li wyraz jest w s³owniku, przepisuje go.
   * W przeciwnym razie próbuje dodaæ znaki diakrytyczne (dodaje wszystkie
   * znalezione wersje).
   * Je¶li i to zawiedzie poszukuje w s³owniku ortograficznym pierwszych piêciu
   * wyrazów przypominaj±cych szukany i dodaje je do listy wyrazów.
   * Gdy nic nie zostanie odnalezione i aktywy jest parametr $copy_unk
   * wyraz w oryginalnej postaci jest przenoszony do listy.
   *
   * @param array $comment Tablica wyrazów do przeanalizowania.
   * @param bool $copy_unk Czy kopiowaæ nieznane wyrazy?
   * @return array Tablica wyrazów lub false, je¶li nie ma wyrazów.
   */
  protected function checkSpelling(array $comment, $copy_unk = false) {
    $tok_comment = array();
    foreach ($comment as $w) {
      if (pspell_check(self::$pspell, $w)) {
        $tok_comment[] = $w;
      } elseif (($temp = self::$fsaa->accent_word($w))) {
        $tok_comment = array_merge($tok_comment, $temp);
      } elseif (strlen($w)>3 && count($temp = pspell_suggest(self::$pspell,$w))>0) {
        $tok_comment = array_merge($tok_comment, array_slice(array_filter($temp,array($this, "pspell_filter")),0,5));
      } else {
        if ($copy_unk)  {
          $tok_comment[] = $w;
        }
      }
    }
    if (count($tok_comment) == 0) return false;
    return $tok_comment;
  }

  /**
   * Dokonuje zamiany wyrazu w wersji odmienionnej leksykalnie do wersji
   * pseudos³ownikowej, redukuj±c tym samym liczbê odmian wyrazu w zbiorze
   * cech.
   *
   * @param array $comment Tablica wyrazów do przeanalizowania.
   * @return array Tablica wyrazów lub false, je¶li nie ma wyrazów.
   */
  protected function lematize(array $comment) {
    $tok_comment = array();
    foreach ($comment as $w) {
      $temp = self::$fsal->lematize($w);
      if ($temp !==false) {
        $tok_comment = array_merge($tok_comment, $temp);
      } else {
        $tok_comment[] = $w;
      }
    }
    if (count($tok_comment) == 0) return false;
    return $tok_comment;
  }

  /**
   * Sprawdza, czy istniej± wulgaryzmy w przekazanej tablicy.
   *
   * @param array $comment Tablica wyrazów do sprawdzenia.
   * @return bool true, je¶li istnieje wulgaryzm lub jest takie podejrzenie
   */
  protected function vulgarism(array $comment) {
    if (self::$validation->findVulgarism($comment) || $this->vulg_prop>0) return true;
    return false;
  }

  /**
   * Dokonuje zamiany tablicy wyrazów na tablicê asocjacyjn± wyrazów, w której
   * kluczamy s± wyrazy a warto¶ciami liczba wyst±pieñ danego wyrazu.
   *
   * @param array $comment Tablica wyrazów do przekszta³cenia.
   * @return array Tablica asocjacyjna czêsto¶ci± wyrazów.
   */
  protected function toFreqList(array $comment) {
    $frq_comment = array_count_values($comment);
    arsort($frq_comment);
    return $frq_comment;
  }

  /**
   * Dokonuje przetworzenia komentarza na tablicê asocjacyjn± z czêsto¶ci±
   * wystêpowania wyrazów. Dokonuje po drodze tokenizacji, walidacji, korekty
   * ortograficznej i lematyzacji.
   *
   * @param string $comment Komentarz do analizy.
   * @return array Tablica asocjacyjna wyrazów lub false, gdy b³êdny komentarz.
   */
  public function doPreparation($comment) {
    if (!$this->validateEmailWWW($comment)) return false;
    $tok_comment = $this->tokenize($comment);
    if ($tok_comment === false) return false;
    $tok_comment = $this->checkSpelling($tok_comment,$this->copy_unknown);
    if ($tok_comment === false) return false;
    $tok_comment = $this->lematize($tok_comment);
    if ($tok_comment === false) return false;
    if ($this->vulgarism($tok_comment)) return false;
    return $this->toFreqList($tok_comment);
  }

  /**
   * Dokonuje klasyfikacji...
   *
   * @param string $comment Komentarz do sklasyfikowania.
   * @param bool[optional] $prepared Czy nie trzeba przetwarzaæ komentarza?
   */
  abstract public function doClassify($comment, $prepared = false);

  /**
   * Dokonuje wstêpnego wype³nienia zbiorów cech danymi przekazanymi w dwóch
   * tablicach. S± to wyrazy z kilku komentarzy wstêpnych.
   * W normalnej pracy klasyfikatora metoda ta nie jest wykorzystywana!
   *
   * @param array $init_comP Komentarze pozytywne.
   * @param array $init_comN Komentarze negatywne.
   */
  abstract public function doInit(array $init_comP, array $init_comN);

  /**
   * Dokonuje aktualizacji zbioru cech.
   * @param mixed $comment Komentarz po przetworzeniu lub jeszcze jako tekst.
   * @param bool $positive Uaktualnij jako pozytywny czy negatywny.
   * @param bool[optional] $prepared Czy nie trzeba przetwarzaæ komentarza?
   */
  abstract public function doUpdate($comment, $positive, $prepared = false);

  /**
    * Funkcja zwrotna wykorzystywana w filtracji danych z aspell, poniewa¿
    * zwraca on równie¿ w propozycjach wyrazy ze spacjami i ³±cznikami.
    *
    * @param string $w
    * @return bool true, je¶li usun±æ wyraz
    */
  private function pspell_filter($w) {
    if (strpos($w,' ',1) !== false) return false;
    if (strpos($w,'-',1) !== false) return false;
    return true;
  }
}
?>