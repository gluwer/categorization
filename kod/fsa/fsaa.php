<?php
/**
Implementacja w j�zyku PHP klasy do obs�ugi plik�w s�ownik�w
ortograficznych formatu FSA w wersji 5.
Klasa dokonuje korekty polskich znak�w diakrytycznych.

Autor: Rafa� Jo�ca <jeffar at fr.pl>
Algorytmy u�yte w rozwi�zaniu pochodz� z prac Jana Daciuka i Kemala Oflazera.
Dodatkowo sam spos�b obs�ugi FSA by� wzorowany na pracy
Wojciecha Rutkowskiego dla projektu SENECA.

Referencje:
- Narz�dzia dla automat�w sko�czonych
  Jana Daciuka (implementacja FSA w j�zyku C):
  http://www.eti.pg.gda.pl/katedry/kiw/pracownicy/Jan.Daciuk/personal/fsa_polski.html
- Projekt SENECA
  (implementacja FSA dla lem�w w j�zyku PHP4)
  http://seneca.kie.ae.poznan.pl/
*/
class Fsaa {
  /** Indeks pocz�tku w�a�ciwych danych automatu stan�w.*/
  private $start = 0x04;
  /** Dane automatu stan�w jako tekst. */
  private $data = "";
  /** Tablica akcent�w, wype�niania w konstruktorze. */
  private $accents = array();
  /** Tablica wynik�w (znalezionych wyraz�w). */
  private $results = array();
  /** Aktualnie analizowany kandydat. */
  private $candidate =" ";
  /** Analizowany wyraz. */
  private $word_ff =" ";

  /** Konstruktor.
   *  Wczytuje automat i inicjalizuje tablic� akcent�w.
   *
   *  @param string $fsa_file Nazwa pliku z automatem stanowym wyraz�w.
   */
  public function __construct ($fsa_file) {
    // za�adowanie i weryfikacja automatu
    $temp = file_get_contents ($fsa_file);
    if (substr($temp,0,4)!="\\fsa") exit("Nieznany format s�ownika");
    if (ord($temp[4])!=5) exit("Nieobs�ugiwana wersja s�ownika");
    if (ord($temp[7])!=3) exit("Nieobs�ugiwany rozmiar danych �uku");
    $this->data = substr($temp,8);

    // inicjalizacja tablicy akcent�w
    for ($i = 0; $i < 256; $i++) {
      $this->accents[chr($i)] = chr($i);
    }
    $this->accents['�']='a'; $this->accents['�']='A';
    $this->accents['�']='c'; $this->accents['�']='C';
    $this->accents['�']='e'; $this->accents['�']='E';
    $this->accents['�']='l'; $this->accents['�']='L';
    $this->accents['�']='n'; $this->accents['�']='N';
    $this->accents['�']='o'; $this->accents['�']='O';
    $this->accents['�']='s'; $this->accents['�']='S';
    $this->accents['�']='z'; $this->accents['�']='Z';
    $this->accents['�']='z'; $this->accents['�']='Z';
  }

  /** Dokonuje korekty diakrytycznej przekazanego wyrazu.
   *
   *  @param string $word Wyraz do analizy.
   *  @return array Zwraca tablic� z wyrazami z uzupe�nion� diakrytyk�.
   *  Je�li brak rozpoznanych wyraz�w, zwraca false.
  */
  public function accent_word($word) {
    // resetuj tablic� wynik�w
    $this->results = array();
    $this->candidate = ' ';
    $this->word_ff = $word;
    $this->find_accents(0, 0, $this->start+2);
    // dokonaj posortowania zast�pie�, je�li s�, zwr�� wynik
    if (count($this->results)) {
      $this->results = array_unique($this->results);
      sort($this->results,SORT_LOCALE_STRING);
      return $this->results;
    }
    return false;
  }

  // Rekurencyjnie szuka wyraz�w z odpowiednimi akcentami.
  private function find_accents($index, $level, $start) {
    $next_node = $start;

    for ($nlast = 1; $nlast; $nlast= !($this->arc_is_last($next_node)), $next_node = $this->next_arc($next_node)) {
      $ch = $this->arc_label($next_node);
      if ($this->word_ff[$index] == $this->accents[$ch] || $this->word_ff[$index] == $ch) {
        $this->candidate[$level] = $ch;
        if (($index+1) == strlen($this->word_ff) && $this->arc_is_final($next_node)) {
	        $this->results[]=$this->candidate;
        } else {
	        $nxt_node =$this->target_node($next_node);
	        $this->find_accents($index + 1, $level + 1, $nxt_node);
        }
      }
    }
  }

  // Zwraca true, je�li �uk o podanym adresie jest �ukiem ko�cowym.
  private function arc_is_final($pos) {
    $t = ord ($this->data[$pos+1]);
    if (($t & 1) == 1) return true;
    else return false;
  }

  // Zwraca true, je�li nast�pnym elementem w ci�gu jest w�ze� docelowy.
  private function target_node_is_next($pos) {
    $t = ord ($this->data[$pos+1]);
    if (($t & 4) == 4) return true;
    else return false;
  }

  // Zwraca true, je�li jest to ostatni �uk analizowanego w�z�a.
  private function arc_is_last($pos) {
    $t = ord ($this->data[$pos+1]);
    if (($t & 2) == 2) return true;
    else return false;
  }

  // Zwraca indeks nast�pnego �uku.
  private function next_arc($pos) {
    if ($this->target_node_is_next($pos))
      return $pos+2;
    else
      return $pos+4;
  }

  // Zwraca adres najbli�szego, nast�pnego w�z�a w ci�gu znak�w.
  private function next_node($pos) {
    $t = $pos;
    while (!$this->target_node_is_next($t)) $t = $this->next_arc($t);
    return $this->next_arc($t);
  }

  // Zwraca etykiet� �uku.
  private function arc_label($pos) {
    return $this->data[$pos];
  }

  // Zwraca indeks w�z�a docelowego dla wskazanego �uku.
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

  // Poszukuje dla w�z�a �uku o podanej etykiecie.
  private function get_arc($pos, $label) {
    $t = $pos;
    if ($label == $this->arc_label($t)) return $t;
    while (!$this->arc_is_last($t)) {
      $t = $this->next_arc($t);
      if ($label == $this->arc_label($t)) return $t;
    }
    return null;
  }
}

?>
