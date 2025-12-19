-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Creato il: Nov 18, 2025 alle 23:11
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
(1, 'verrie', '$2y$10$ZV/mw9RyrdyzynG.Au8tK.SFyjCbaezBcFlWZUhFbdjsjlAkBQ2p6', 'base'),
(3, 'zain', '$2y$10$U8i4DFuCPS.5drIlfS.YPORTdblqQtr8d3TUEnxdiPIahA3fLhGhS', 'base');

-- --------------------------------------------------------

--
-- Struttura della tabella `prodotti`
--

CREATE TABLE `prodotti` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descrizione` varchar(255) NOT NULL,
  `quantita` int(11) NOT NULL,
  `allarme` varchar(255) NOT NULL,
  `fornitore` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `prodotti`
--

INSERT INTO `prodotti` (`id`, `nome`, `descrizione`, `quantita`, `allarme`, `fornitore`) VALUES
(1, 'prova', 'vetro', 52, 'nessuno', 'fornitore'),
(2, 'prova2', 'pvc', 1, 'attivo', 'fornitore'),
(3, 'prova3', 'prova', 1, 'attivo', 'prova'),
(4, 'prova4', 'prova', 2, 'nessuno', 'prova'),
(5, 'prova5', 'prova', 7, 'nessuno', 'prova'),
(6, 'prov4', 'prova', 44, 'nessuno', 'prova'),
(7, 'prova8', 'prova', 6, 'nessuno', 'prova'),
(8, 'prova9', 'prova', 15, 'nessuno', 'prova'),
(9, 'prova10', 'prova', 7, 'nessuno', 'prova'),
(10, 'prova11', 'prova', 1, 'attivo', 'prova'),
(11, 'prova12', 'prova', 7, 'nessuno', 'prova');

-- --------------------------------------------------------

--
-- Struttura della tabella `storicomovimenti`
--

CREATE TABLE `storicomovimenti` (
  `id` int(11) NOT NULL,
  `idProdotto` varchar(255) NOT NULL,
  `movimento` varchar(255) NOT NULL,
  `idUtente` varchar(255) NOT NULL,
  `descrizione` varchar(255) NOT NULL,
  `dataMovimento` varchar(255) NOT NULL,
  `bollaNumero` varchar(255) NOT NULL,
  `datoNumero` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `storicomovimenti`
--

INSERT INTO `storicomovimenti` (`id`, `idProdotto`, `movimento`, `idUtente`, `descrizione`, `dataMovimento`, `bollaNumero`, `datoNumero`) VALUES
(1, 'prova', '1', 'verrie', '', '20/10/2025 22:13', '', ''),
(2, 'prova', '-20', 'verrie', '', '22/10/2025 22:13', '', ''),
(3, 'prova', '1', 'verrie', '', '22/10/2025 22:23', '', ''),
(4, 'prova2', '-1', 'verrie', '', '22/10/2025 22:24', '', ''),
(5, 'prova', '-31', 'verrie', '', '22/10/2025 22:24', '', ''),
(6, 'prova', '20', 'verrie', '', '22/10/2025 22:29', '', ''),
(7, 'prova2', '1', 'verrie', '', '22/10/2025 22:38', '', ''),
(8, 'prova2', '1', 'verrie', '', '22/10/2025 22:38', '', ''),
(9, 'prova2', '1', 'verrie', '', '22/10/2025 22:38', '', ''),
(10, 'prova', '1', 'verrie', '', '22/10/2025 22:45', '', ''),
(11, 'prova', '-10', 'verrie', '', '22/10/2025 22:45', '', ''),
(12, 'prova2', '-6', 'verrie', '', '22/10/2025 22:45', '', ''),
(13, 'prova3', '-1', 'verrie', '', '22/10/2025 22:54', '', ''),
(14, 'prova', '40', 'verrie', '', '22/10/2025 23:52', '', ''),
(15, 'prov4', '20', 'verrie', '', '22/10/2025 23:52', '', ''),
(16, 'prova3', '5', 'verrie', '1234', '23/10/2025 11:05', '1234', ''),
(17, 'prova3', '-6', 'verrie', '', '23/10/2025 11:06', '', ''),
(18, 'prova9', '7', 'verrie', '', '23/10/2025 11:06', '', ''),
(19, 'prova9', '5', 'verrie', '', '23/10/2025 16:42', '', ''),
(20, 'prov4', '22', 'verrie', 'weeee', '17/11/2025 00:08', 'wewe', ''),
(21, 'prova10', '5', 'verrie', '', '17/11/2025 00:11', 'wewe', 'wewweee'),
(22, 'prova11', '-3', 'verrie', 'weeee', '17/11/2025 00:11', 'weee', 'weeee');

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `login`
--
ALTER TABLE `login`
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
-- AUTO_INCREMENT per la tabella `prodotti`
--
ALTER TABLE `prodotti`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT per la tabella `storicomovimenti`
--
ALTER TABLE `storicomovimenti`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
