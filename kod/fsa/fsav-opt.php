<?php
/**
Implementacja w j�zyku PHP klasy do obs�ugi plik�w s�ownik�w
wyrazowych formatu FSA w wersji 5.
Klasa sprawdza jedynie, czy wyraz znajduje si� w s�owniku.

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
class Fsav {
  /** Indeks pocz�tku w�a�ciwych danych automatu stan�w.*/
  private $start = 0x04;
  /** Dane automatu stan�w jako tekst. */
  private $data = "";

  /** Konstruktor.
   *  Wczytuje automat i inicjalizuje tablic� akcent�w.
   *
   *  @param string $fsa_file Nazwa pliku z automatem stanowym wyraz�w.
   */
  public function __construct($fsa_file) {
    // za�adowanie i weryfikacja automatu
    $temp = file_get_contents($fsa_file);
    if (substr($temp,0,4)!="\\fsa") exit("Nieznany format s�ownika");
    if (ord($temp[4])!=5) exit("Nieobs�ugiwana wersja s�ownika");
    if (ord($temp[7])!=2) exit("Nieobs�ugiwany rozmiar danych �uku");
    $this->data = substr($temp,8);
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
    return ((ord($this->data[$t+1]) & 4) == 4)?$t+1:$t+3;
  }

  // Zwraca indeks w�z�a docelowego dla wskazanego �uku.
  private function target_node($pos) {
    if ((ord($this->data[$pos+1]) & 4) == 4) {
      return $this->next_node($pos);
    } else {
      $t = 0;
      for ($i=2; $i>0; $i--) {
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
      $t += ((ord($this->data[$t+1]) & 4) == 4)?1:3;
      if ($label == $this->data[$t]) return $t;
    }
    return null;
  }
}

?>
