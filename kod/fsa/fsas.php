<?php
/**
Implementacja w jêzyku PHP klasy do obs³ugi plików s³owników
ortograficznych formatu FSA w wersji 5.

Autor: Rafa³ Joñca <jeffar at fr.pl>
Algorytmy u¿yte w rozwi±zaniu pochodz± z prac Jana Daciuka i Kemala Oflazera.
Dodatkowo sam sposób obs³ugi FSA by³ wzorowany na pracy
Wojciecha Rutkowskiego dla projektu SENECA.

Referencje:
- Narzêdzia dla automatów skoñczonych
  Jana Daciuka (implementacja FSA w jêzyku C):
  http://www.eti.pg.gda.pl/katedry/kiw/pracownicy/Jan.Daciuk/personal/fsa_polski.html
- Projekt SENECA
  (implementacja FSA dla lemów w jêzyku PHP4)
  http://seneca.kie.ae.poznan.pl/
*/
class Fsas {
  /** Indeks pocz±tku w³a¶ciwych danych automatu stanów.*/
  private $start = 0x04;
  /** Dane automatu stanów jako tekst. */
  private $data = "";

  /** Macierz H jako wektor. */
  private $Hmatrix = array();
  /** Liczba wierszy macierzy. */
  private	$row_length;
  /** Maksymalna d³ugo¶æ wyrazu. */
  private $max_length = 40;

  // Dodatkowe zast±pienia (rz->¿, ±->o³ itp.), wspomaga poprawê b³êdów ortograficznych.
  /** Tablica zast±pieñ jedna litera->dwie litery. */
  private $first_column = array ('¿'=>'rz','±'=>'on|o³|om','ê'=>'en|em');
  /** Tablica zast±pieñ dwie litery->jedna litera. */
  private $second_column = array ('¿'=>'rz','±'=>'on|o³|om');

  /** Lista wyników po¶rednich/tablic z ocen±. */
  private $results = array();
  /** Aktualnie analizowany kandydat. */
  private $candidate =" ";
  /** Analizowany wyraz. */
  private $word_ff =" ";

  /** Konstruktor.
   *  Wczytuje automat.
   *
   *  @param string $fsa_file Nazwa pliku z automatem stanowym wyrazów.
   */
  public function __construct($fsa_file) {
    // za³adowanie i weryfikacja automatu
    $temp = file_get_contents($fsa_file);
    if (substr($temp,0,4)!="\\fsa") exit("Nieznany format s³ownika");
    if (ord($temp[4])!=5) exit("Nieobs³ugiwana wersja s³ownika");
    if (ord($temp[7])!=3) exit("Nieobs³ugiwany rozmiar danych ³uku");
    $this->data = substr($temp,8);
  }

  /** Inicjalizuje symulowan± macierz H. */
  private function init_Hmatrix() {
    $this->row_length = $this->max_length + 2;
    $size = $this->row_length * 5;
    for ($i = 0; $i < $this->row_length - 2; $i++) {
      $this->Hmatrix[$i] = 2;		          // H(distance + j, j) = distance + 1
      $this->Hmatrix[$size - $i - 1] = 2;	// H(i, distance + i) = distance + 1
    }
    for ($j = 0; $j < 3; $j++) {
      $this->Hmatrix[$j * $this->row_length] = 2 - $j;	      // H(i=0..distance+1,0)=i
      $this->Hmatrix[($j + 2) * $this->row_length + $j] = $j;	// H(0,j=0..distance+1)=j
    }
  }

  // Porównuje dwóch tablic ranked_list.
  private function comp_ranked_list(&$it1, &$it2) {
    if (($c = strcmp($it1['list_item'], $it2['list_item'])) == 0) {
      if ($it1['dist'] < $it2['dist']) $it1['dist'] = $it2['dist'];
    }
    return $c;
  }

  // Pobiera element symulowanej macierzy Hmatrix.
  private function get_Hmatrix_el($i, $j) {
    return $this->Hmatrix[($j - $i + 2) * $this->row_length + $j];
  }

  // Ustawia element symulowanej macierzy Hmatrix.
  private function set_Hmatrix_el($i, $j, $val) {
    $this->Hmatrix[($j - $i + 2) * $this->row_length + $j] = $val;
  }

  /** Sprawd¼, czy wyraz znajduje siê w s³owniku. Je¶li nie,
   *  dokonaj korekty ortograficznej i zwróæ mo¿liwe zast±pienia.
   *  Je¶li nie znajdziesz zast±pieñ o odleg³o¶ci edycyjnej <=1, zwróæ false.
   *  @param string $word Wyraz do analizy.
   *  @return array true - je¶li wyraz w s³owniku
   *          tablicê s³ów - je¶li wyraz nie w s³owniku, ale znaleziono zast±pienia
   *          false - brak wyrazu w s³owniku i nie znaleziono dla niego zast±pieñ
   */
  public function spell_word($word) {
    // resetuj tablicê wyników i macierz H
    $this->results = array();
    $this->init_Hmatrix();
    // poszukaj, czy wystêpuje w s³owniku
    if ($this->find_word($word))
      return true;
    // rozpocznij poszukiwanie zast±pieñ
    $this->word_ff = $word;
    $this->candidate = " ";
    $this->find_repl(0, $this->start, 0, 0);
    // dokonaj posortowania zast±pieñ, je¶li s±, i zwróæ je
    if (count($this->results)) {
      return $this->rank_replacements();
    }
    return false;
  }

  // Tworzy listê kandydatów dla b³êdnego wyrazu, wywo³ywana rekurencyjnie.
  private function find_repl($depth, $start,$word_index, $cand_index) {

    $next_node =$this->target_node($start);
    $dist = 0;
    $word_found = array();
    $word_length = strlen($this->word_ff);

    for ($nlast = 1; $nlast; $nlast= !($this->arc_is_last($next_node)), $next_node = $this->next_arc($next_node)) {

      $this->candidate{$cand_index} = $this->arc_label($next_node);
      // B³±d ort. przej¶cia z jednej litery na dwie...
      if ($this->match_candidate($word_index, $cand_index)) {
        $this->find_repl($depth, $next_node, $word_index, $cand_index + 1);

        if (abs($word_length - 1 - $depth) <= 1 && $this->arc_is_final($next_node) &&
	         ($dist = $this->ed($word_length - 2 - ($word_index - $depth), $depth - 2,
		       $word_length - 2, $cand_index - 2)) + 1 <= 1) {
	        $this->candidate = substr($this->candidate,0,$cand_index + 1);
        	$word_found['list_item'] = $this->candidate;
        	$word_found['dist'] = $dist;
        	$word_found['cost'] = $dist;
        	$this->results_insert_sorted($word_found);
        }
      }

      if ($this->cuted($depth, $word_index, $cand_index) <= 1) {
        $this->find_repl($depth + 1, $next_node, $word_index + 1, $cand_index + 1);
        // B³±d ort. przej¶cia z dwóch liter liter na jedn±...
        if ($this->match_word($word_index, $cand_index)) {
	        $this->find_repl($depth + 1, $next_node, $word_index + 2, $cand_index + 1);
	        if (abs($word_length - 1 - $depth) <= 1 && $this->arc_is_final($next_node) &&
	           ($word_length > 2 && $this->match_word($word_length - 2, $cand_index) &&
	           ($dist = $this->ed($word_length - 3 - ($word_index - $depth), $depth - 1,
			        $word_length - 3, $cand_index -1) + 1) <= 1)) {
          	$word_found['list_item'] = $this->candidate;
          	$word_found['dist'] = $dist;
          	$word_found['cost'] = $dist;
          	$this->results_insert_sorted($word_found);
	        }
        }
        // Zwyk³y b³±d typograficzny.
        $this->candidate = substr($this->candidate,0,$cand_index +1);
        if (abs($word_length - 1 - $depth) <= 1 && $this->arc_is_final($next_node) &&
	         ($dist = $this->ed($word_length - 1 - ($word_index - $depth), $depth,
		        $word_length - 1, $cand_index) <= 1)) {
          	$word_found['list_item'] = $this->candidate;
          	$word_found['dist'] = $dist;
          	$word_found['cost'] = $dist;
          	$this->results_insert_sorted($word_found);
        }
      }
    }
  }

  // Wstawia wynik do posortowanej listy.
  private function results_insert_sorted(&$word_found) {
    $c = 0;
    for ($next_item = 0; $next_item < count($this->results); $next_item++) {
      if (($c = $this->comp_ranked_list($word_found, $this->results[$next_item])) > 0) {
        for ($ni = count($this->results)-1; $ni >= $next_item; --$ni)
          $this->results[$ni+1] =$this->results[$ni];
        $this->results[$next_item]=$word_found;
        return true;
      } else if ($c == 0) { // element jest, nic z nim nie rób
        return false;
      }
    }
    // wstaw nowy
    $this->results[]=$word_found;
    return true;
  }

  // Wylicza odleg³o¶æ miêdzy wyrazami.
  private function ed($i, $j, $word_index, $cand_index) {
    if ($this->word_ff{$word_index} == $this->candidate{$cand_index}) {
      // ostatnie znaki s± takie same
      $result = $this->get_Hmatrix_el($i, $j);
    } else if ($word_index > 0 && $cand_index > 0 &&
  	   $this->word_ff[$word_index] == $this->candidate[$cand_index - 1] &&
  	   $this->word_ff[$word_index - 1] == $this->candidate[$cand_index]) {
      // zamiana dwóch ostatnich znaków
      $a = $this->get_Hmatrix_el($i - 1, $j - 1);	// zamiana, e.g. ababab, ababba
      $b = $this->get_Hmatrix_el($i + 1, $j);		// usuniêcie,      e.g. abab,   aba
      $c = $this->get_Hmatrix_el($i, $j + 1);		// wstawienie      e.g. aba,    abab
      $result = 1 + min($a, $b, $c);
    } else {
      // otherwise
      $a = $this->get_Hmatrix_el($i, $j);		// zast±pienie,   e.g. ababa,  ababb
      $b = $this->get_Hmatrix_el($i + 1, $j);		// usuniêcie,      e.g. ab,     a
      $c = $this->get_Hmatrix_el($i, $j + 1);		// wstawienie      e.g. a,      ab
      $result = 1 + min($a, $b, $c);
    }

    $this->set_Hmatrix_el($i + 1, $j + 1, $result);
    return $result;
  }

  // Wylicza przyciêt± odleg³o¶æ wyrazów.
  private function cuted($depth, $word_index, $cand_index) {
    $l = max(0, $depth - 1);
    $u = min(strlen($this->word_ff) - 1 - ($word_index - $depth), $depth + 1);
    $min_ed = 2;
    $wi = $word_index + $l - $depth;

    for ($i = $l; $i <= $u; $i++, $wi++) {
      if (($d = $this->ed($i, $depth, $wi, $cand_index)) < $min_ed)
        $min_ed = $d;
    }
    return $min_ed;
  }

  // Sprawdza zast±pienie ortograficzne (jeden znak->dwa znaki).
  private function match_candidate($i, $j) {
    $c = $this->first_column[$this->word_ff{$i-1}];
    if ($i > 0 && isset($c) && $j > 0) {
      $le = strlen($c);
      for ($c0 = $this->candidate{$j-1}, $c1 = $this->candidate{$j}, $id = 0; $id<$le; $id+= 3)
        if ($c{$id} == $c0 && $c{$id+1} == $c1)
  	      return true;
  	  }
    return false;
  }

  // Sprawdza zast±pienie ortograficzne (dwa znaki->jeden znak).
  private function match_word($i, $j) {
    $c = $this->second_column[$this->candidate[$j]];
    if (isset($c)) {
      $le = strlen($c);
      for ($c0 = $this->word_ff[$i], $c1 = $this->word_ff[$i+1], $id = 0; $id<$le; $id+= 3)
        if ($c[$id] == $c0 && $c[$id+1] == $c1)
  	      return true;
    }
    return false;
  }

  // Zwraca tablicê zast±pieñ posortowan± wed³ug kosztu.
  private function rank_replacements() {
    $r = array();

    usort($this->results, array($this,'cmp'));
    for ($i = 0; $i < count($this->results); $i++)
      $r[]=$this->results[$i]['list_item'];
    return $r;
  }

  /** Sprawd¼, czy wyraz znajduje siê w s³owniku.
   *  @param string $word Wyraz do analizy.
   *  @return bool true - je¶li wyraz w s³owniku
   *          false - brak wyrazu w s³owniku
   */
  public function find_word($word) {
    $p = $this->start;
    $p = $this->get_arc($p, "^");
    $p = $this->target_node($p);
    for ($i=0; $i<strlen($word); $i++) {
      $p = $this->get_arc($p,$word{$i});
      if ($p) {
        if ($i==strlen($word)-1 && $this->arc_is_final($p)) return true;
        $p = $this->target_node($p);
      } else return false;
    }
    return false;
  }

  // Zwraca true, je¶li ³uk o podanym adresie jest ³ukiem koñcowym.
  private function arc_is_final($pos) {
    $t = ord ($this->data[$pos+1]);
    if (($t & 1) == 1) return true;
    else return false;
  }

  // Zwraca true, je¶li nastêpnym elementem w ci±gu jest wêze³ docelowy.
  private function target_node_is_next($pos) {
    $t = ord ($this->data[$pos+1]);
    if (($t & 4) == 4) return true;
    else return false;
  }

  // Zwraca true, je¶li jest to ostatni ³uk analizowanego wêz³a.
  private function arc_is_last($pos) {
    $t = ord ($this->data[$pos+1]);
    if (($t & 2) == 2) return true;
    else return false;
  }

  // Zwraca indeks nastêpnego ³uku
  private function next_arc($pos) {
    if ($this->target_node_is_next($pos))
      return $pos+2;
    else
      return $pos+4;
  }

  // Zwraca adres najbli¿szego, nastêpnego wêz³a w ci±gu znaków.
  private function next_node($pos) {
    $t = $pos;
    while (!$this->target_node_is_next($t)) $t = $this->next_arc($t);
    return $this->next_arc($t);
  }

  // Zwraca etykietê ³uku.
  private function arc_label($pos) {
    return $this->data[$pos];
  }

  // Zwraca indeks wêz³a docelowego dla wskazanego ³uku.
  private function target_node($pos) {
    if ($this->target_node_is_next($pos)) {
      return $this->next_node($pos);
    } else {
      $t = 0;
      for ($i=3; $i>0; $i--) {
        $t <<= 8;
        $t |= (ord($this->data[$pos+$i]) & 0xff);
      }
      $t >>= 3;
      return $t;
    }
  }

  // Poszukuje dla wêz³a ³uku o podanej etykiecie.
  private function get_arc($pos, $label) {
    $t = $pos;
    if ($label == $this->arc_label($t)) return $t;
    while (!$this->arc_is_last($t)) {
      $t = $this->next_arc($t);
      if ($label == $this->arc_label($t)) return $t;
    }
    return null;
  }

  // U¿ywana do porównywania elementów.
  private function cmp($a, $b) {
    if ($b["cost"] == $a["cost"])
    return strcmp($a["list_item"], $b["list_item"]);
    return ($a["cost"] < $b["cost"]) ? 1 : -1;
  }
}


?>
