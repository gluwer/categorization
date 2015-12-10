<?php
/**
Implementacja w j�zyku PHP klasy do obs�ugi plik�w s�ownik�w
ortograficznych formatu FSA w wersji 5.

Autor: Rafa� Jo�ca <jeffar at fr.pl>
Algorytmy u�yte w rozwi�zaniu pochodz� z prac Jana Daciuka i Kemala Oflazera.
Dodatkowo sam spos�b obs�ugi FSA by� wzorowany na pracy
Wojciecha Rutkowskiego dla projektu SENECA.

Referencje:
- Narz�dzia dla automat�w sko�czonych
  Jana Daciuka (implementacja FSA w j�zyku C):
  http://www.eti.pg.gda.pl/katedry/kiw/pracownicy/Jan.Daciuk/personal/fsa_polski.html
- Projekt SENECA
  (implementacja FSA dla lemat�w w j�zyku PHP4)
  http://seneca.kie.ae.poznan.pl/
*/
class Fsas {
  /** Indeks pocz�tku w�a�ciwych danych automatu stan�w.*/
  private $start = 0x04;
  /** Dane automatu stan�w jako tekst. */
  private $data = "";

  /** Macierz H jako wektor. */
  private $Hmatrix = array();

  // Dodatkowe zast�pienia (rz->�, �->o� itp.), wspomaga popraw� b��d�w ortograficznych.
  /** Tablica zast�pie� jedna litera->dwie litery. */
  private $first_column = array ('�'=>'rz','�'=>'on|o�|om','�'=>'en|em');
  /** Tablica zast�pie� dwie litery->jedna litera. */
  private $second_column = array ('�'=>'rz','�'=>'on|o�|om');

  /** Lista wynik�w po�rednich/tablic z ocen�. */
  private $results = array();
  /** Aktualnie analizowany kandydat. */
  private $candidate =" ";
  /** Analizowany wyraz. */
  private $word_ff =" ";

  /** Konstruktor.
   *  Wczytuje automat.
   *
   *  @param string $fsa_file Nazwa pliku z automatem stanowym wyraz�w.
   */
  public function __construct($fsa_file) {
    // za�adowanie i weryfikacja automatu
    $temp = file_get_contents($fsa_file);
    if (substr($temp,0,4)!="\\fsa") exit("Nieznany format s�ownika");
    if (ord($temp[4])!=5) exit("Nieobs�ugiwana wersja s�ownika");
    if (ord($temp[7])!=3) exit("Nieobs�ugiwany rozmiar danych �uku");
    $this->data = substr($temp,8);
  }

  // Inicjalizuje symulowan� macierz H.
  private function init_Hmatrix() {
    for ($i = 0; $i < 40; $i++) {
      $this->Hmatrix[$i] = 2;		    // H(distance + j, j) = distance + 1
      $this->Hmatrix[209 - $i] = 2;	// H(i, distance + i) = distance + 1
    }
    for ($j = 0; $j < 3; $j++) {
      $this->Hmatrix[$j * 42] = 2 - $j;	       // H(i=0..distance+1,0)=i
      $this->Hmatrix[($j + 2) * 42 + $j] = $j;	// H(0,j=0..distance+1)=j
    }
  }

  // Por�wnuje dw�ch tablic ranked_list.
  private function comp_ranked_list(&$it1, &$it2) {
    if (($c = strcmp($it1['list_item'], $it2['list_item'])) == 0) {
      if ($it1['dist'] < $it2['dist']) $it1['dist'] = $it2['dist'];
    }
    return $c;
  }

  /** Sprawd�, czy wyraz znajduje si� w s�owniku. Je�li nie,
   *  dokonaj korekty ortograficznej i zwr�� mo�liwe zast�pienia.
   *  Je�li nie znajdziesz zast�pie� o odleg�o�ci edycyjnej <=1, zwr�� false.
   *
   *  @param string $word Wyraz do analizy.
   *  @return array true - je�li wyraz w s�owniku
   *          tablic� s��w - je�li wyraz nie w s�owniku, ale znaleziono zast�pienia
   *          false - brak wyrazu w s�owniku i nie znaleziono dla niego zast�pie�
   */
  public function spell_word($word) {
    // resetuj tablic� wynik�w i macierz H
    $this->results = array();
    $this->init_Hmatrix();
    // poszukaj, czy wyst�puje w s�owniku
    if ($this->find_word($word))
      return true;
    // rozpocznij poszukiwanie zast�pie�
    $this->word_ff = $word;
    $this->candidate = " ";
    $this->find_repl(0, $this->start, 0, 0);
    // dokonaj posortowania zast�pie�, je�li s�, i zwr�� je
    if (count($this->results)) {
      return $this->rank_replacements();
    }
    return false;
  }

  // Tworzy list� kandydat�w dla b��dnego wyrazu, wywo�ywana rekurencyjnie.
  private function find_repl($depth, $start,$word_index, $cand_index) {

    $next_node =$this->target_node($start);
    $dist = 0;
    $word_found = array();
    $word_length = strlen($this->word_ff);

    for ($nlast = 1; $nlast; $nlast= !((ord($this->data{$next_node+1}) & 2) == 2), $next_node += ((ord($this->data[$next_node+1]) & 4) == 4)?2:4) {

      $this->candidate{$cand_index} = $this->data[$next_node];
      // B��d ort. przej�cia z jednej litery na dwie...
      if ($this->match_candidate($word_index, $cand_index)) {
        $this->find_repl($depth, $next_node, $word_index, $cand_index + 1);

        if (abs($word_length - 1 - $depth) <= 1 && ((ord($this->data[$next_node+1]) & 1) == 1) &&
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
        // B��d ort. przej�cia z dw�ch liter liter na jedn�...
        if ($this->match_word($word_index, $cand_index)) {
	        $this->find_repl($depth + 1, $next_node, $word_index + 2, $cand_index + 1);
	        if (abs($word_length - 1 - $depth) <= 1 && ((ord($this->data[$next_node+1]) & 1) == 1) &&
	           ($word_length > 2 && $this->match_word($word_length - 2, $cand_index) &&
	           ($dist = $this->ed($word_length - 3 - ($word_index - $depth), $depth - 1,
			        $word_length - 3, $cand_index -1) + 1) <= 1)) {
          	$word_found['list_item'] = $this->candidate;
          	$word_found['dist'] = $dist;
          	$word_found['cost'] = $dist;
          	$this->results_insert_sorted($word_found);
	        }
        }
        // Zwyk�y b��d typograficzny.
        $this->candidate = substr($this->candidate,0,$cand_index +1);
        if (abs($word_length - 1 - $depth) <= 1 && ((ord($this->data[$next_node+1]) & 1) == 1) &&
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
      } else if ($c == 0) { // element jest, nic z nim nie r�b
        return false;
      }
    }
    // wstaw nowy
    $this->results[]=$word_found;
    return true;
  }

  // Wylicza odleg�o�� mi�dzy wyrazami.
  private function ed($i, $j, $word_index, $cand_index) {
    if ($this->word_ff{$word_index} == $this->candidate{$cand_index}) {
      // ostatnie znaki s� takie same
      $result = $this->Hmatrix[($j - $i + 2) * 42 + $j];
    } else if ($word_index > 0 && $cand_index > 0 &&
  	   $this->word_ff[$word_index] == $this->candidate[$cand_index - 1] &&
  	   $this->word_ff[$word_index - 1] == $this->candidate[$cand_index]) {
       $result = 1 + min($this->Hmatrix[($j - $i + 2) * 42 + $j - 1],
                         $this->Hmatrix[($j - $i + 1) * 42 + $j],
                         $this->Hmatrix[($j - $i + 3) * 42 + $j + 1]);
    } else {
      // otherwise
      $result = 1 + min($this->Hmatrix[($j - $i + 2) * 42 + $j],
                        $this->Hmatrix[($j - $i + 1) * 42 + $j],
                        $this->Hmatrix[($j - $i + 3) * 42 + $j + 1]);
    }

    $this->Hmatrix[($j - $i + 2) * 42 + $j + 1] = $result;
    return $result;
  }

  // Wylicza przyci�t� odleg�o�� wyraz�w.
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

  // Sprawdza zast�pienie ortograficzne (jeden znak->dwa znaki).
  private function match_candidate($i, $j) {
    $c = $this->first_column[$this->word_ff[$i-1]];
    if ($i > 0 && isset($c) && $j > 0) {
      $le = strlen($c);
      for ($c0 = $this->candidate[$j-1], $c1 = $this->candidate[$j], $id = 0; $id<$le; $id+= 3)
        if ($c[$id] == $c0 && $c[$id+1] == $c1)
  	      return true;
  	  }
    return false;
  }

  // Sprawdza zast�pienie ortograficzne (dwa znaki->jeden znak).
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

  // Zwraca tablic� zast�pie� posortowan� wed�ug kosztu.
  private function rank_replacements() {
    $r = array();
    usort($this->results,array ($this, 'cmp'));
    for ($i = 0; $i < count($this->results); $i++)
      $r[]=$this->results[$i]['list_item'];
    return $r;
  }

  /** Sprawd�, czy wyraz znajduje si� w s�owniku.
   *
   *  @param string $word Wyraz do analizy.
   *  @return bool true - je�li wyraz w s�owniku
   *          false - brak wyrazu w s�owniku
   */
  public function find_word($word) {
    $p = $this->start;
    $p = $this->get_arc($p, "^");
    $p = $this->target_node($p);
    for ($i=0; $i<strlen($word); $i++) {
      $p = $this->get_arc($p,$word{$i});
      if ($p) {
        if ($i==strlen($word)-1 && ((ord($this->data{$p+1}) & 1) == 1)) return true;
        $p = $this->target_node($p);
      } else return false;
    }
    return false;
  }

  // Zwraca adres najbli�szego, nast�pnego w�z�a w ci�gu znak�w.
  private function next_node($pos) {
    $t = $pos;
    while (!((ord($this->data[$t+1]) & 4) == 4)) $t += ((ord($this->data[$t+1]) & 4) == 4)?2:4;
    return ((ord($this->data[$t+1]) & 4) == 4)?$t+2:$t+4;
  }

  // Zwraca indeks w�z�a docelowego dla wskazanego �uku.
  private function target_node($pos) {
    if ((ord($this->data[$pos+1]) & 4) == 4) {
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

  // Poszukuje dla w�z�a �uku o podanej etykiecie.
  private function get_arc($pos, $label) {
    $t = $pos;
    if ($label == $this->data[$t]) return $t;
    while (!((ord($this->data[$t+1]) & 2) == 2)) {
      $t += ((ord($this->data[$t+1]) & 4) == 4)?2:4;
      if ($label == $this->data[$t]) return $t;
    }
    return null;
  }

  // U�ywana do por�wnywania element�w.
  private function cmp($a, $b) {
    if ($b["cost"] == $a["cost"])
    return strcmp($a["list_item"], $b["list_item"]);
    return ($a["cost"] < $b["cost"]) ? 1 : -1;
  }

}



?>