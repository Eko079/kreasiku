-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Waktu pembuatan: 19 Nov 2025 pada 14.45
-- Versi server: 8.0.43-0ubuntu0.24.04.2
-- Versi PHP: 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kreasiku`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `comments`
--

CREATE TABLE `comments` (
  `id` int NOT NULL,
  `design_id` int NOT NULL,
  `user_id` int NOT NULL,
  `body` text COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int NOT NULL,
  `name` varchar(190) NOT NULL,
  `email` varchar(190) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `designs`
--

CREATE TABLE `designs` (
  `id` int NOT NULL,
  `owner_id` int NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `category` enum('portofolio','website','cv','desain','logo') COLLATE utf8mb4_general_ci NOT NULL,
  `visibility` enum('public','private') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'public',
  `status` enum('draft','scheduled','published') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'published',
  `allow_download` tinyint(1) NOT NULL DEFAULT '0',
  `scheduled_at` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `comments_count` int NOT NULL DEFAULT '0',
  `likes_count` int NOT NULL DEFAULT '0',
  `saves_count` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `kind` enum('image','figma') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'image',
  `media_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `figma_url` text COLLATE utf8mb4_general_ci,
  `allow_comments` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `design_images`
--

CREATE TABLE `design_images` (
  `id` int NOT NULL,
  `design_id` int NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `position` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `mime` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `design_likes`
--

CREATE TABLE `design_likes` (
  `id` int NOT NULL,
  `design_id` int NOT NULL,
  `user_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `design_saves`
--

CREATE TABLE `design_saves` (
  `id` int NOT NULL,
  `design_id` int NOT NULL,
  `user_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `magic_tokens`
--

CREATE TABLE `magic_tokens` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `email` varchar(190) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `request_ip` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `request_log`
--

CREATE TABLE `request_log` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `avatar_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `avatar_url` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kelas` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bio` text COLLATE utf8mb4_general_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_d` (`design_id`),
  ADD KEY `idx_comments_design` (`design_id`,`id`);

--
-- Indeks untuk tabel `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email_idx` (`email`);

--
-- Indeks untuk tabel `designs`
--
ALTER TABLE `designs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_owner` (`owner_id`),
  ADD KEY `idx_cat` (`category`),
  ADD KEY `idx_status_sched` (`status`,`scheduled_at`),
  ADD KEY `idx_designs_pub` (`status`,`visibility`,`published_at`),
  ADD KEY `idx_designs_cat` (`category`,`status`,`visibility`,`updated_at`),
  ADD KEY `idx_designs_owner` (`owner_id`,`updated_at`),
  ADD KEY `idx_cat_vis_created` (`category`,`visibility`,`created_at`);

--
-- Indeks untuk tabel `design_images`
--
ALTER TABLE `design_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_design` (`design_id`);

--
-- Indeks untuk tabel `design_likes`
--
ALTER TABLE `design_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_like` (`design_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `design_saves`
--
ALTER TABLE `design_saves`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_save` (`design_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `magic_tokens`
--
ALTER TABLE `magic_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `token_hash_idx` (`token_hash`),
  ADD KEY `expires_idx` (`expires_at`),
  ADD KEY `fk_magic_user` (`user_id`);

--
-- Indeks untuk tabel `request_log`
--
ALTER TABLE `request_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_action` (`user_id`,`action`,`created_at`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT untuk tabel `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `designs`
--
ALTER TABLE `designs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT untuk tabel `design_images`
--
ALTER TABLE `design_images`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `design_likes`
--
ALTER TABLE `design_likes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT untuk tabel `design_saves`
--
ALTER TABLE `design_saves`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT untuk tabel `magic_tokens`
--
ALTER TABLE `magic_tokens`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT untuk tabel `request_log`
--
ALTER TABLE `request_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`design_id`) REFERENCES `designs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `designs`
--
ALTER TABLE `designs`
  ADD CONSTRAINT `designs_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `design_images`
--
ALTER TABLE `design_images`
  ADD CONSTRAINT `design_images_ibfk_1` FOREIGN KEY (`design_id`) REFERENCES `designs` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `design_likes`
--
ALTER TABLE `design_likes`
  ADD CONSTRAINT `design_likes_ibfk_1` FOREIGN KEY (`design_id`) REFERENCES `designs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `design_likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `design_saves`
--
ALTER TABLE `design_saves`
  ADD CONSTRAINT `design_saves_ibfk_1` FOREIGN KEY (`design_id`) REFERENCES `designs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `design_saves_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `magic_tokens`
--
ALTER TABLE `magic_tokens`
  ADD CONSTRAINT `fk_magic_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
