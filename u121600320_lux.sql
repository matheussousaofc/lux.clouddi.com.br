-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 11/11/2026 às 21:20
-- Versão do servidor: 11.8.9-MariaDB-log
-- Versão do PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `u121600320_lux`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `professional_id` int(11) DEFAULT NULL,
  `data_hora_inicio` datetime NOT NULL,
  `data_hora_fim` datetime NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `status` enum('confirmado','cancelado','realizado') DEFAULT 'confirmado',
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `blocked_slots`
--

CREATE TABLE `blocked_slots` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `professional_id` int(11) DEFAULT NULL,
  `data_inicio` datetime NOT NULL,
  `data_fim` datetime NOT NULL,
  `motivo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `business_hours`
--

CREATE TABLE `business_hours` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `professional_id` int(11) DEFAULT NULL,
  `dia_semana` tinyint(4) NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fim` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `cash_registers`
--

CREATE TABLE `cash_registers` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `aberto_por` int(11) NOT NULL,
  `data_abertura` datetime NOT NULL,
  `saldo_inicial` decimal(10,2) NOT NULL,
  `data_fechamento` datetime DEFAULT NULL,
  `saldo_final_informado` decimal(10,2) DEFAULT NULL,
  `saldo_final_calculado` decimal(10,2) DEFAULT NULL,
  `diferenca` decimal(10,2) DEFAULT NULL,
  `status` enum('aberto','fechado') DEFAULT 'aberto',
  `observacao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `cash_transactions`
--

CREATE TABLE `cash_transactions` (
  `id` int(11) NOT NULL,
  `cash_register_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `tipo` enum('entrada','saida') NOT NULL,
  `origem` enum('manual','sugerido_atendimento') DEFAULT 'manual',
  `valor` decimal(10,2) NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `categoria` varchar(80) DEFAULT NULL,
  `criado_em` datetime DEFAULT current_timestamp(),
  `criado_por` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `nome` varchar(120) NOT NULL,
  `telefone` varchar(20) NOT NULL,
  `data_nascimento` date DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `companies`
--

CREATE TABLE `companies` (
  `id` int(11) NOT NULL,
  `nome_fantasia` varchar(160) NOT NULL,
  `razao_social` varchar(160) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `email_contato` varchar(160) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `status_mensalidade` enum('ativa','atrasada','suspensa','cancelada') DEFAULT 'ativa',
  `plano` varchar(80) DEFAULT NULL,
  `valor_mensalidade` decimal(10,2) DEFAULT NULL,
  `dia_vencimento` tinyint(4) DEFAULT NULL,
  `criado_em` datetime DEFAULT current_timestamp(),
  `ativo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `companies`
--

INSERT INTO `companies` (`id`, `nome_fantasia`, `razao_social`, `telefone`, `email_contato`, `foto`, `cpf`, `status_mensalidade`, `plano`, `valor_mensalidade`, `dia_vencimento`, `criado_em`, `ativo`) VALUES
(1, 'Teste', 'Empresa teste', '38999148001', 'devmatheussousa@gmail.com', NULL, NULL, 'ativa', 'Mensal', 100.00, 10, '2026-11-11 14:45:44', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `company_settings`
--

CREATE TABLE `company_settings` (
  `company_id` int(11) NOT NULL,
  `intervalo_agendamento_min` int(11) NOT NULL DEFAULT 30,
  `antecedencia_minima_min` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `company_settings`
--

INSERT INTO `company_settings` (`company_id`, `intervalo_agendamento_min`, `antecedencia_minima_min`) VALUES
(1, 30, 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `master_users`
--

CREATE TABLE `master_users` (
  `id` int(11) NOT NULL,
  `nome` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `master_users`
--

INSERT INTO `master_users` (`id`, `nome`, `email`, `senha_hash`, `criado_em`) VALUES
(1, 'Matheus Sousa', 'devmatheussousa@gmail.com', '$2y$10$zUxfXNCWqhdNQbZ4SxASauc2CtynY/IXVI62mnY/6CmfraGyXfvvC', '2026-11-11 14:31:20');

-- --------------------------------------------------------

--
-- Estrutura para tabela `owner_users`
--

CREATE TABLE `owner_users` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `nome` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `owner_users`
--

INSERT INTO `owner_users` (`id`, `company_id`, `nome`, `email`, `senha_hash`, `ativo`, `criado_em`) VALUES
(1, 1, 'Matheus Sousa', 'devmatheussousa@gmail.com', '$2y$10$J0zBCPQ0ZaFsB3VsUSPLeO4RJ.urYZExkT2/G4Q4HZqM7BCptp8nS', 1, '2026-11-11 14:45:44');

-- --------------------------------------------------------

--
-- Estrutura para tabela `professionals`
--

CREATE TABLE `professionals` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `nome` varchar(120) NOT NULL,
  `cargo` varchar(80) DEFAULT NULL,
  `cor_agenda` varchar(9) DEFAULT '#8b5cf6',
  `aparece_agendamento_online` tinyint(1) DEFAULT 1,
  `ativo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `professional_services`
--

CREATE TABLE `professional_services` (
  `professional_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `nome` varchar(120) NOT NULL,
  `descricao` text DEFAULT NULL,
  `duracao_minutos` int(11) NOT NULL,
  `preco` decimal(10,2) NOT NULL,
  `ativo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `servicos`
--

CREATE TABLE `servicos` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `duracao_min` int(11) NOT NULL DEFAULT 30,
  `preco` decimal(10,2) NOT NULL DEFAULT 0.00,
  `categoria` varchar(100) DEFAULT NULL,
  `status` enum('ativo','inativo') NOT NULL DEFAULT 'ativo',
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `professional_id` (`professional_id`);

--
-- Índices de tabela `blocked_slots`
--
ALTER TABLE `blocked_slots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `professional_id` (`professional_id`);

--
-- Índices de tabela `business_hours`
--
ALTER TABLE `business_hours`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `professional_id` (`professional_id`);

--
-- Índices de tabela `cash_registers`
--
ALTER TABLE `cash_registers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `aberto_por` (`aberto_por`);

--
-- Índices de tabela `cash_transactions`
--
ALTER TABLE `cash_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cash_register_id` (`cash_register_id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `criado_por` (`criado_por`);

--
-- Índices de tabela `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`);

--
-- Índices de tabela `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `company_settings`
--
ALTER TABLE `company_settings`
  ADD PRIMARY KEY (`company_id`);

--
-- Índices de tabela `master_users`
--
ALTER TABLE `master_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Índices de tabela `owner_users`
--
ALTER TABLE `owner_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `owner_users_company_fk` (`company_id`);

--
-- Índices de tabela `professionals`
--
ALTER TABLE `professionals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`);

--
-- Índices de tabela `professional_services`
--
ALTER TABLE `professional_services`
  ADD PRIMARY KEY (`professional_id`,`service_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Índices de tabela `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`);

--
-- Índices de tabela `servicos`
--
ALTER TABLE `servicos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `blocked_slots`
--
ALTER TABLE `blocked_slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `business_hours`
--
ALTER TABLE `business_hours`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `cash_registers`
--
ALTER TABLE `cash_registers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `cash_transactions`
--
ALTER TABLE `cash_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `master_users`
--
ALTER TABLE `master_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `owner_users`
--
ALTER TABLE `owner_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `professionals`
--
ALTER TABLE `professionals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `servicos`
--
ALTER TABLE `servicos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  ADD CONSTRAINT `appointments_ibfk_4` FOREIGN KEY (`professional_id`) REFERENCES `professionals` (`id`);

--
-- Restrições para tabelas `blocked_slots`
--
ALTER TABLE `blocked_slots`
  ADD CONSTRAINT `blocked_slots_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  ADD CONSTRAINT `blocked_slots_ibfk_2` FOREIGN KEY (`professional_id`) REFERENCES `professionals` (`id`);

--
-- Restrições para tabelas `business_hours`
--
ALTER TABLE `business_hours`
  ADD CONSTRAINT `business_hours_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  ADD CONSTRAINT `business_hours_ibfk_2` FOREIGN KEY (`professional_id`) REFERENCES `professionals` (`id`);

--
-- Restrições para tabelas `cash_registers`
--
ALTER TABLE `cash_registers`
  ADD CONSTRAINT `cash_registers_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  ADD CONSTRAINT `cash_registers_ibfk_2` FOREIGN KEY (`aberto_por`) REFERENCES `owner_users` (`id`);

--
-- Restrições para tabelas `cash_transactions`
--
ALTER TABLE `cash_transactions`
  ADD CONSTRAINT `cash_transactions_ibfk_1` FOREIGN KEY (`cash_register_id`) REFERENCES `cash_registers` (`id`),
  ADD CONSTRAINT `cash_transactions_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`),
  ADD CONSTRAINT `cash_transactions_ibfk_3` FOREIGN KEY (`criado_por`) REFERENCES `owner_users` (`id`);

--
-- Restrições para tabelas `clients`
--
ALTER TABLE `clients`
  ADD CONSTRAINT `clients_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`);

--
-- Restrições para tabelas `company_settings`
--
ALTER TABLE `company_settings`
  ADD CONSTRAINT `company_settings_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`);

--
-- Restrições para tabelas `owner_users`
--
ALTER TABLE `owner_users`
  ADD CONSTRAINT `owner_users_company_fk` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `professionals`
--
ALTER TABLE `professionals`
  ADD CONSTRAINT `professionals_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`);

--
-- Restrições para tabelas `professional_services`
--
ALTER TABLE `professional_services`
  ADD CONSTRAINT `professional_services_ibfk_1` FOREIGN KEY (`professional_id`) REFERENCES `professionals` (`id`),
  ADD CONSTRAINT `professional_services_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`);

--
-- Restrições para tabelas `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`);

--
-- Restrições para tabelas `servicos`
--
ALTER TABLE `servicos`
  ADD CONSTRAINT `servicos_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
