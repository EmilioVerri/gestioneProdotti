-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Creato il: Dic 19, 2025 alle 11:40
-- Versione del server: 10.4.32-MariaDB
-- Versione PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `gestioneprodotti`
--

-- --------------------------------------------------------

--
-- Struttura della tabella `login`
--

CREATE TABLE `login` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `privilegi` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `login`
--

INSERT INTO `login` (`id`, `username`, `password`, `privilegi`) VALUES
(1, 'verrie', '$2y$10$ZV/mw9RyrdyzynG.Au8tK.SFyjCbaezBcFlWZUhFbdjsjlAkBQ2p6', 'admin'),
(3, 'zain', '$2y$10$U8i4DFuCPS.5drIlfS.YPORTdblqQtr8d3TUEnxdiPIahA3fLhGhS', 'base');

-- --------------------------------------------------------

--
-- Struttura della tabella `padre`
--

CREATE TABLE `padre` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `padre`
--

INSERT INTO `padre` (`id`, `nome`) VALUES
(6, 'wewe'),
(9, 'ciaoweila'),
(10, 'patrick la scimmia');

-- --------------------------------------------------------

--
-- Struttura della tabella `prodotti`
--

CREATE TABLE `prodotti` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descrizione` varchar(255) NOT NULL,
  `quantita` decimal(10,1) NOT NULL DEFAULT 0.0,
  `allarme` varchar(255) NOT NULL,
  `fornitore` varchar(255) NOT NULL,
  `padre` varchar(255) NOT NULL,
  `minimo` decimal(10,1) NOT NULL DEFAULT 0.0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `prodotti`
--

INSERT INTO `prodotti` (`id`, `nome`, `descrizione`, `quantita`, `allarme`, `fornitore`, `padre`, `minimo`) VALUES
(164, 'Telaio', '', 7.0, 'nessuno', '', 'wewe', 2.0),
(165, 'Telaio Restauro', '', 4.0, 'nessuno', '', 'wewe', 1.0),
(166, 'Anta', '', 11.0, 'nessuno', '', 'wewe', 7.0),
(167, 'Anta Maniglia Passante', '', 7.0, 'nessuno', '', 'wewe', 3.0),
(168, 'Scambio Battuta', '', 10.0, 'nessuno', '', 'wewe', 4.0),
(169, 'Traverso Telaio da mm. 104', '', 1.0, 'attivo', '', 'wewe', 2.0),
(175, 'amen', 'nata', 21.5, 'nessuno', 'nn', 'ciaoweila', 1.0),
(176, 'ehi', 'cena', 4.0, 'nessuno', 'aa', 'ciaoweila', 4.0),
(177, 'scimmia', '', 2.0, 'nessuno', '', 'patrick la scimmia', 4.0);

-- --------------------------------------------------------

--
-- Struttura della tabella `storicomovimenti`
--

CREATE TABLE `storicomovimenti` (
  `id` int(11) NOT NULL,
  `idProdotto` varchar(255) NOT NULL,
  `movimento` varchar(20) NOT NULL,
  `idUtente` varchar(255) NOT NULL,
  `descrizione` varchar(255) NOT NULL,
  `dataMovimento` varchar(255) NOT NULL,
  `bollaNumero` varchar(255) NOT NULL,
  `datoNumero` varchar(255) NOT NULL,
  `idPadre` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `storicomovimenti`
--

INSERT INTO `storicomovimenti` (`id`, `idProdotto`, `movimento`, `idUtente`, `descrizione`, `dataMovimento`, `bollaNumero`, `datoNumero`, `idPadre`) VALUES
(23, 'Anta Maniglia Passante', '5', 'verrie', '', '18/12/2025 20:59', '', '', 'wewe'),
(24, 'Traverso Telaio da mm. 104', '-5', 'verrie', '', '18/12/2025 21:01', '', '', 'wewe'),
(25, 'ciao boss', '6', 'verrie', '', '18/12/2025 22:03', '', '', '8000prova'),
(26, 'ehi', '6', 'verrie', '', '19/12/2025 11:07', '', '', 'ciaoweila'),
(27, 'ehi', '-3', 'verrie', '', '19/12/2025 11:07', '', '', 'ciaoweila'),
(28, 'ehi', '-4', 'verrie', '', '19/12/2025 11:07', '', '', 'ciaoweila'),
(29, 'ehi', '-1', 'verrie', '', '19/12/2025 11:07', '', '', 'ciaoweila'),
(30, 'ehi', '3', 'verrie', '', '19/12/2025 11:07', '', '', 'ciaoweila'),
(31, 'ehi', '7', 'verrie', '', '19/12/2025 11:08', '', '', 'ciaoweila'),
(32, 'amen', '7', 'verrie', '', '19/12/2025 11:11', '', '', 'ciaoweila'),
(33, 'amen', '2', 'verrie', '', '19/12/2025 11:12', '', '', 'ciaoweila'),
(34, 'amen', '7', 'verrie', '', '19/12/2025 11:25', '', '', 'ciaoweila'),
(35, 'ehi', '-6', 'verrie', 'nanna', '19/12/2025 11:25', 'wewe', 'wewe', 'ciaoweila'),
(36, 'amen', '2', 'verrie', 'nano', '19/12/2025 11:25', 'alber', 'wewe', 'ciaoweila'),
(37, 'amen', '1.5', 'verrie', '', '19/12/2025 11:39', '', '', 'ciaoweila');

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `login`
--
ALTER TABLE `login`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `padre`
--
ALTER TABLE `padre`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `prodotti`
--
ALTER TABLE `prodotti`
  ADD PRIMARY KEY (`id`);

--
-- Indici per le tabelle `storicomovimenti`
--
ALTER TABLE `storicomovimenti`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT per le tabelle scaricate
--

--
-- AUTO_INCREMENT per la tabella `login`
--
ALTER TABLE `login`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT per la tabella `padre`
--
ALTER TABLE `padre`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT per la tabella `prodotti`
--
ALTER TABLE `prodotti`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=178;

--
-- AUTO_INCREMENT per la tabella `storicomovimenti`
--
ALTER TABLE `storicomovimenti`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
