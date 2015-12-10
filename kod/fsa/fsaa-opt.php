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
  (implementacja FSA dla lemat�w w j�zyku PHP4)
  http://seneca.kie.ae.poznan.pl/
*/
class Fsaa {
  /** Indeks pocz�tku w�a�ciwych danych automatu stan�w. */
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
   *          Je�li brak rozpoznanych wyraz�w, zwraca false.
   */
  public function accent_word($word) {
    // resetuj tablic� wynik�w
    $this->results = array();
    $this->candidate=' ';
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

    for ($nlast = 1; $nlast; $nlast= !((ord($this->data{$next_node+1}) & 2) == 2), $next_node += ((ord($this->data[$next_node+1]) & 4) == 4)?2:4) {
      $ch = $this->data[$next_node];
      if ($this->word_ff[$index] == $this->accents[$ch] || $this->word_ff[$index] == $ch) {
        $this->candidate[$level] = $ch;
        if (($index+1) == strlen($this->word_ff) && ((ord($this->data[$next_node+1]) & 1) == 1)) {
	        $this->results[]=$this->candidate;
        } else {
          if ((ord($this->data{$next_node+1}) & 4) == 4) {
            $nxt_node = $next_node;
            while (!((ord($this->data[$nxt_node+1]) & 4) == 4)) $nxt_node += ((ord($this->data[$nxt_node+1]) & 4) == 4)?2:4;
            if ((ord($this->data[$nxt_node+1]) & 4) == 4)
              $nxt_node+=2;
            else
              $nxt_node+=4;
          } else {
            $t = 0;
            for ($i=3; $i>0; $i--) {
              $t <<= 8;
              $t |= (ord($this->data[$next_node+$i]) & 0xff);
            }
            $t >>= 3;
            $nxt_node = $t;
          }
	        $this->find_accents($index + 1, $level + 1, $nxt_node);
        }
      }
    }
  }
}

?>