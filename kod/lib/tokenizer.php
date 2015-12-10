<?php
/**
 * Klasa tokenizera
 * Dzieli tekst na wyrazy, szybka implementacja o liniowej z³o¿ono¶ci.
 * Szczegó³y podzia³u w dokumentacji metody tokenize().
 *
 * Autor: Rafa³ Joñca <jeffar at fr.pl>
 */
class Tokenizer {

  // Sta³e tokenizera.
  const TSEP = 0;
  const TDIG = 1;
  const TLET = 2;

  /**
   * Odwzorowanie zamiany wielkich liter na ma³e.
   *
   * @var array
   */
  private $map = array();

  /**
   * Przechowuje typy poszczególnych znaków.
   *
   * @var array
   */
  private $type = array();

  /**
   * Przechowuje wyrazy stoplisty.
   *
   * @var array
   */
  private $stoplist = array();

  /**
   * Inicjalizuje tokenizer, tworz±c odpowiednie tablice przekszta³ceñ.
   * Sama klasa tokoenizera jest bezstanowa.
   *
   * @param string Nazwa pliku ze s³owami stoplisty.
   */
  function __construct($stoplist = '') {
    // przygotowanie tablic
    for ($i= 0; $i<256; $i++) {
      if ($i>=48 && $i<58) {
        $this->type[chr($i)] = Tokenizer::TDIG;
      } elseif ($i>=97 && $i<123) {
        $this->type[chr($i)] = Tokenizer::TLET;
      } else {
        $this->type[chr($i)] = Tokenizer::TSEP;
      }
      $this->map[chr($i)]=chr($i);
    }
    // wielkie litery
    for ($i=65; $i< 91; $i++) {
      $this->type[chr($i)] = Tokenizer::TLET;
      $this->map[chr($i)] = chr($i+32);
    }
    // polskie znaki wg ISO8859-2 (Latin2)
    $polish = array(161,198,202,163,209,211,166,175,172,
    177,230,234,179,241,243,182,191,188);
    for ($i=0; $i<9; $i++) {
      $this->type[chr($polish[$i])] = Tokenizer::TLET;
      $this->type[chr($polish[$i+9])] = Tokenizer::TLET;
      $this->map[chr($polish[$i])] = chr($polish[$i+9]);
    }
    if ($stoplist != '') {
      $handle = @fopen($stoplist, "r");
      if ($handle) {
        while (!feof($handle)) {
          $this->stoplist[] = substr(fgets($handle, 20),0,-1);
        }
        fclose($handle);
      }
    }
  }

  /**
   * Tokenuzuje tekst zgodnie z poni¿szymi zasadami:
   * - zamiana wielkich liter na ma³e,
   * - usuwanie powtórzeñ liter, wiêc kkkkaaallkaa zmienia siê w kalka,
   * - pomijanie wyrazów jednoznakowych i dwuznakowych,
   * - niezale¿nie traktowanie liczb,
   * - zamiana liczb na wyrazy przedzia³owe,
   * - je¶li przy inicjalizacji zosta³ podany plik stoplisty, usuwa
   *   wyrazy ze stoplisty,
   * - ostatnim zwracanym elementem jest zawsze informacja o potencjalnych
   *   wulgaryzmach (liczba wyst±pieñ co najmniej czterech **** pod rz±d).
   *
   * @param string $s Tekst do tokenizacji.
   * @return array Tablica z rozdzielonymi wyrazami.
   */
  public function tokenize ($s) {
    // przygotuj zmienne
    $re = array();
    $size = strlen($s);
    $let = '';
    $dig = '';
    $char = "\0";
    $repeats = 0;
    $vulg = 0;
    for ($i=0; $i<$size; $i++) {
      // sprawd¼, czy to nie powtórzenie znaku
      $c = $this->map[$s[$i]];
      if ($c == $char && $this->type[$c] != Tokenizer::TDIG) {
        if ($c == '*') ++$repeats;
        continue;
      }
      if ($repeats>2) {
        ++$vulg;
        $repeats=0;
      }
      $char = $c;
      // sprawd¼ typ znaku
      if ($this->type[$char] == Tokenizer::TSEP) {
        if ($dig) { // liczby
          $re[] = $this->toText($dig);
        }
        if (strlen($let)>2 && !in_array($let, $this->stoplist)) { // tekst co najmniej trzyznakowy
          $re[] = $let;
        }
        $let = '';
        $dig = '';
        $repeats=0;
      } elseif ($this->type[$char] == Tokenizer::TDIG) {
        $dig .= $char;
      } else {
        $let .= $char;
      }
    }
    if ($dig) { // liczby
      $re[] = $this->toText($dig);
    }
    if (strlen($let)>1) { // tekst co najmniej dwuznakowy
      $re[] = $let;
    }
    if ($repeats>2) {
      ++$vulg;
      $repeats=0;
    }
    $re[] = $vulg;
    return $re;
  }

  /**
   * Zamienia warto¶æ liczbow± na warto¶æ tekstow±
   * ilustruj±c± w prosty sposób przedzia³ liczbowy.
   *
   * @param int $t Liczba dodatnia.
   * @return string
   */
  private function toText($t) {
    if ($t == 0) return 'zero';
    if ($t < 10) return 'jedynka';
    if ($t < 100) return 'dziesi±tka';
    if ($t < 1000) return 'setka';
    if ($t < 100000) return 'tysi±c';
    return 'milion';
  }

  public function checkOnStoplist($list) {
    $ok = array();
    foreach ($list as $w) {
    	if (!in_array($w, $this->stoplist)) {
    	  $ok[] = $w;
    	}
    }
    return $ok;
  }
}


/*$T = new Tokenizer('../dict/stoplist.txt');
//print_r($T->sep);
$s = "¶wietne minki!jak cpunki
nuuuuuuuuuuuuuuuuuuuuddddddddaaaa
to jest jakas powalona gra!! co prawda lepsza od Love Bob''a, ale jest g³upia ...
moj rekord to 7886
Dzi? mój pingwin zastrajkowa³ i nie mogê go wyrzuciæ poza 267,30.Pomó¿cie.
5 plansza jest okropna
kupa
Ja tylko Do 3 lvl doszlem :(
Jak tego bosa nieda rady pokonac ile ten bos ma zyc??
trudne
naprawde to nie jest fajna
NIE MOZNA TEGO WLACZYC
szczur taka tylko gre mug³ wymyslec!!!!!
h****A";
echo "<pre>\n";
print_r($T->tokenize($s));
echo "\n</pre>";*/
?>