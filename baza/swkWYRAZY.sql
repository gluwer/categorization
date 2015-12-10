-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_1-1n`
-- 

CREATE TABLE `datavect_1-1n` (
  `word` varchar(25) character set latin2 collate latin2_bin NOT NULL,
  `wp` double NOT NULL,
  `wn` double NOT NULL,
  PRIMARY KEY  (`word`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_1-1n`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_1-1p`
-- 

CREATE TABLE `datavect_1-1p` (
  `word` varchar(25) character set latin2 collate latin2_bin NOT NULL,
  `wp` double NOT NULL,
  `wn` double NOT NULL,
  PRIMARY KEY  (`word`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_1-1p`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_1-2`
-- 

CREATE TABLE `datavect_1-2` (
  `word` varchar(25) character set latin2 collate latin2_bin NOT NULL,
  `dfikp` int(11) NOT NULL,
  `dfikn` int(11) NOT NULL,
  `WCikp` double NOT NULL,
  `WCikn` double NOT NULL,
  `CCi` double NOT NULL,
  `widp` double NOT NULL,
  `widn` double NOT NULL,
  PRIMARY KEY  (`word`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_1-2`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_1-2o`
-- 

CREATE TABLE `datavect_1-2o` (
  `Nkp` int(11) NOT NULL default '0',
  `Nkn` int(11) NOT NULL default '0'
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_1-2o`
-- 

INSERT INTO `datavect_1-2o` VALUES (273, 196);

-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_1-3f`
-- 

CREATE TABLE `datavect_1-3f` (
  `word` varchar(25) character set latin2 collate latin2_bin NOT NULL,
  `dfikp` int(11) NOT NULL,
  `dfikn` int(11) NOT NULL,
  `WCikp` double NOT NULL,
  `WCikn` double NOT NULL,
  `CCi` double NOT NULL,
  `idf` double NOT NULL,
  `sump` double NOT NULL default '0',
  `sumn` double NOT NULL default '0',
  PRIMARY KEY  (`word`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_1-3f`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_1-3o`
-- 

CREATE TABLE `datavect_1-3o` (
  `Nkp` int(10) unsigned NOT NULL default '0',
  `Nkn` int(10) unsigned NOT NULL default '0'
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_1-3o`
-- 

INSERT INTO `datavect_1-3o` VALUES (269, 169);

-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_1-3p`
-- 

CREATE TABLE `datavect_1-3p` (
  `word` varchar(25) character set latin2 collate latin2_bin NOT NULL,
  `subcat` smallint(5) unsigned NOT NULL,
  `sum` double NOT NULL,
  PRIMARY KEY  (`word`,`subcat`),
  KEY `subcat_i` (`subcat`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_1-3p`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_1-3s`
-- 

CREATE TABLE `datavect_1-3s` (
  `pn` enum('p','n') NOT NULL,
  `subcat` smallint(5) unsigned NOT NULL auto_increment,
  `sim` double NOT NULL,
  `num` int(10) unsigned NOT NULL,
  `last_act` smallint(5) unsigned NOT NULL,
  PRIMARY KEY  (`subcat`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2 AUTO_INCREMENT=1 ;

-- 
-- Zrzut danych tabeli `datavect_1-3s`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_1-4f`
-- 

CREATE TABLE `datavect_1-4f` (
  `word` varchar(25) character set latin2 collate latin2_bin NOT NULL,
  `dfikp` int(11) NOT NULL,
  `dfikn` int(11) NOT NULL,
  `WCikp` double NOT NULL,
  `WCikn` double NOT NULL,
  `CCi` double NOT NULL,
  `idf` double NOT NULL,
  PRIMARY KEY  (`word`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_1-4f`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_1-4o`
-- 

CREATE TABLE `datavect_1-4o` (
  `Nkp` int(10) unsigned NOT NULL default '0',
  `Nkn` int(10) unsigned NOT NULL default '0'
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_1-4o`
-- 

INSERT INTO `datavect_1-4o` VALUES (229, 168);

-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_1-4p`
-- 

CREATE TABLE `datavect_1-4p` (
  `word` varchar(25) character set latin2 collate latin2_bin NOT NULL,
  `subcat` smallint(5) unsigned NOT NULL,
  `weight` double NOT NULL,
  PRIMARY KEY  (`word`,`subcat`),
  KEY `subcat_i` (`subcat`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_1-4p`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_1-4s`
-- 

CREATE TABLE `datavect_1-4s` (
  `subcat` smallint(5) unsigned NOT NULL auto_increment,
  `pn` enum('p','n') NOT NULL,
  `last_act` smallint(5) unsigned NOT NULL,
  PRIMARY KEY  (`subcat`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2 AUTO_INCREMENT=1 ;

-- 
-- Zrzut danych tabeli `datavect_1-4s`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_2-1n`
-- 

CREATE TABLE `datavect_2-1n` (
  `word` varchar(25) character set latin2 collate latin2_bin NOT NULL,
  `wp` double NOT NULL,
  `wn` double NOT NULL,
  PRIMARY KEY  (`word`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_2-1n`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_2-1p`
-- 

CREATE TABLE `datavect_2-1p` (
  `word` varchar(25) character set latin2 collate latin2_bin NOT NULL,
  `wp` double NOT NULL,
  `wn` double NOT NULL,
  PRIMARY KEY  (`word`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_2-1p`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_2-2`
-- 

CREATE TABLE `datavect_2-2` (
  `word` varchar(25) character set latin2 collate latin2_bin NOT NULL,
  `dfikp` int(11) NOT NULL,
  `dfikn` int(11) NOT NULL,
  `WCikp` double NOT NULL,
  `WCikn` double NOT NULL,
  `CCi` double NOT NULL,
  `widp` double NOT NULL,
  `widn` double NOT NULL,
  PRIMARY KEY  (`word`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_2-2`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_2-2o`
-- 

CREATE TABLE `datavect_2-2o` (
  `Nkp` int(11) NOT NULL default '0',
  `Nkn` int(11) NOT NULL default '0'
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_2-2o`
-- 

INSERT INTO `datavect_2-2o` VALUES (229, 205);

-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_2-3f`
-- 

CREATE TABLE `datavect_2-3f` (
  `word` varchar(25) character set latin2 collate latin2_bin NOT NULL,
  `dfikp` int(11) NOT NULL,
  `dfikn` int(11) NOT NULL,
  `WCikp` double NOT NULL,
  `WCikn` double NOT NULL,
  `CCi` double NOT NULL,
  `idf` double NOT NULL,
  `sump` double NOT NULL default '0',
  `sumn` double NOT NULL default '0',
  PRIMARY KEY  (`word`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_2-3f`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_2-3o`
-- 

CREATE TABLE `datavect_2-3o` (
  `Nkp` int(10) unsigned NOT NULL default '0',
  `Nkn` int(10) unsigned NOT NULL default '0'
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_2-3o`
-- 

INSERT INTO `datavect_2-3o` VALUES (209, 172);

-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_2-3p`
-- 

CREATE TABLE `datavect_2-3p` (
  `word` varchar(25) character set latin2 collate latin2_bin NOT NULL,
  `subcat` smallint(5) unsigned NOT NULL,
  `sum` double NOT NULL,
  PRIMARY KEY  (`word`,`subcat`),
  KEY `subcat_i` (`subcat`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_2-3p`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_2-3s`
-- 

CREATE TABLE `datavect_2-3s` (
  `pn` enum('p','n') NOT NULL,
  `subcat` smallint(5) unsigned NOT NULL auto_increment,
  `sim` double NOT NULL,
  `num` int(10) unsigned NOT NULL,
  `last_act` smallint(5) unsigned NOT NULL,
  PRIMARY KEY  (`subcat`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2 AUTO_INCREMENT=1 ;

-- 
-- Zrzut danych tabeli `datavect_2-3s`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_2-4f`
-- 

CREATE TABLE `datavect_2-4f` (
  `word` varchar(25) character set latin2 collate latin2_bin NOT NULL,
  `dfikp` int(11) NOT NULL,
  `dfikn` int(11) NOT NULL,
  `WCikp` double NOT NULL,
  `WCikn` double NOT NULL,
  `CCi` double NOT NULL,
  `idf` double NOT NULL,
  PRIMARY KEY  (`word`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_2-4f`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_2-4o`
-- 

CREATE TABLE `datavect_2-4o` (
  `Nkp` int(10) unsigned NOT NULL default '0',
  `Nkn` int(10) unsigned NOT NULL default '0'
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_2-4o`
-- 

INSERT INTO `datavect_2-4o` VALUES (209, 175);

-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_2-4p`
-- 

CREATE TABLE `datavect_2-4p` (
  `word` varchar(25) character set latin2 collate latin2_bin NOT NULL,
  `subcat` smallint(5) unsigned NOT NULL,
  `weight` double NOT NULL,
  PRIMARY KEY  (`word`,`subcat`),
  KEY `subcat_i` (`subcat`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_2-4p`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_2-4s`
-- 

CREATE TABLE `datavect_2-4s` (
  `subcat` smallint(5) unsigned NOT NULL auto_increment,
  `pn` enum('p','n') NOT NULL,
  `last_act` smallint(5) unsigned NOT NULL,
  PRIMARY KEY  (`subcat`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2 AUTO_INCREMENT=1 ;

-- 
-- Zrzut danych tabeli `datavect_2-4s`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_3-1n`
-- 

CREATE TABLE `datavect_3-1n` (
  `word` varchar(25) character set latin2 collate latin2_bin NOT NULL,
  `wp` double NOT NULL,
  `wn` double NOT NULL,
  PRIMARY KEY  (`word`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_3-1n`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_3-1p`
-- 

CREATE TABLE `datavect_3-1p` (
  `word` varchar(25) character set latin2 collate latin2_bin NOT NULL,
  `wp` double NOT NULL,
  `wn` double NOT NULL,
  PRIMARY KEY  (`word`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_3-1p`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_3-2`
-- 

CREATE TABLE `datavect_3-2` (
  `word` varchar(25) character set latin2 collate latin2_bin NOT NULL,
  `dfikp` int(11) NOT NULL,
  `dfikn` int(11) NOT NULL,
  `WCikp` double NOT NULL,
  `WCikn` double NOT NULL,
  `CCi` double NOT NULL,
  `widp` double NOT NULL,
  `widn` double NOT NULL,
  PRIMARY KEY  (`word`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_3-2`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_3-2o`
-- 

CREATE TABLE `datavect_3-2o` (
  `Nkp` int(11) NOT NULL default '0',
  `Nkn` int(11) NOT NULL default '0'
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_3-2o`
-- 

INSERT INTO `datavect_3-2o` VALUES (63, 130);

-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_3-3f`
-- 

CREATE TABLE `datavect_3-3f` (
  `word` varchar(25) character set latin2 collate latin2_bin NOT NULL,
  `dfikp` int(11) NOT NULL,
  `dfikn` int(11) NOT NULL,
  `WCikp` double NOT NULL,
  `WCikn` double NOT NULL,
  `CCi` double NOT NULL,
  `idf` double NOT NULL,
  `sump` double NOT NULL default '0',
  `sumn` double NOT NULL default '0',
  PRIMARY KEY  (`word`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_3-3f`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_3-3o`
-- 

CREATE TABLE `datavect_3-3o` (
  `Nkp` int(10) unsigned NOT NULL default '0',
  `Nkn` int(10) unsigned NOT NULL default '0'
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_3-3o`
-- 

INSERT INTO `datavect_3-3o` VALUES (53, 85);

-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_3-3p`
-- 

CREATE TABLE `datavect_3-3p` (
  `word` varchar(25) character set latin2 collate latin2_bin NOT NULL,
  `subcat` smallint(5) unsigned NOT NULL,
  `sum` double NOT NULL,
  PRIMARY KEY  (`word`,`subcat`),
  KEY `subcat_i` (`subcat`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_3-3p`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_3-3s`
-- 

CREATE TABLE `datavect_3-3s` (
  `pn` enum('p','n') NOT NULL,
  `subcat` smallint(5) unsigned NOT NULL auto_increment,
  `sim` double NOT NULL,
  `num` int(10) unsigned NOT NULL,
  `last_act` smallint(5) unsigned NOT NULL,
  PRIMARY KEY  (`subcat`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2 AUTO_INCREMENT=1 ;

-- 
-- Zrzut danych tabeli `datavect_3-3s`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_3-4f`
-- 

CREATE TABLE `datavect_3-4f` (
  `word` varchar(25) character set latin2 collate latin2_bin NOT NULL,
  `dfikp` int(11) NOT NULL,
  `dfikn` int(11) NOT NULL,
  `WCikp` double NOT NULL,
  `WCikn` double NOT NULL,
  `CCi` double NOT NULL,
  `idf` double NOT NULL,
  PRIMARY KEY  (`word`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_3-4f`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_3-4o`
-- 

CREATE TABLE `datavect_3-4o` (
  `Nkp` int(10) unsigned NOT NULL default '0',
  `Nkn` int(10) unsigned NOT NULL default '0'
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_3-4o`
-- 

INSERT INTO `datavect_3-4o` VALUES (54, 83);

-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_3-4p`
-- 

CREATE TABLE `datavect_3-4p` (
  `word` varchar(25) character set latin2 collate latin2_bin NOT NULL,
  `subcat` smallint(5) unsigned NOT NULL,
  `weight` double NOT NULL,
  PRIMARY KEY  (`word`,`subcat`),
  KEY `subcat_i` (`subcat`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2;

-- 
-- Zrzut danych tabeli `datavect_3-4p`
-- 


-- --------------------------------------------------------

-- 
-- Struktura tabeli dla  `datavect_3-4s`
-- 

CREATE TABLE `datavect_3-4s` (
  `subcat` smallint(5) unsigned NOT NULL auto_increment,
  `pn` enum('p','n') NOT NULL,
  `last_act` smallint(5) unsigned NOT NULL,
  PRIMARY KEY  (`subcat`)
) ENGINE=MyISAM DEFAULT CHARSET=latin2 AUTO_INCREMENT=1 ;

-- 
-- Zrzut danych tabeli `datavect_3-4s`
-- 

