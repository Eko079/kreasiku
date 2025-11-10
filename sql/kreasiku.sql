-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 10 Nov 2025 pada 19.47
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

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
  `id` int(11) NOT NULL,
  `design_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `comments`
--

INSERT INTO `comments` (`id`, `design_id`, `user_id`, `body`, `created_at`) VALUES
(5, 9, 3, 'pahri emang ganteng', '2025-11-10 12:32:47');

-- --------------------------------------------------------

--
-- Struktur dari tabel `designs`
--

CREATE TABLE `designs` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `category` enum('portofolio','website','cv','desain','logo') NOT NULL,
  `visibility` enum('public','private') NOT NULL DEFAULT 'public',
  `status` enum('draft','scheduled','published') NOT NULL DEFAULT 'published',
  `allow_download` tinyint(1) NOT NULL DEFAULT 0,
  `scheduled_at` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `comments_count` int(11) NOT NULL DEFAULT 0,
  `likes_count` int(11) NOT NULL DEFAULT 0,
  `saves_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `kind` enum('image','figma') NOT NULL DEFAULT 'image',
  `media_path` varchar(255) DEFAULT NULL,
  `figma_url` text DEFAULT NULL,
  `allow_comments` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `designs`
--

INSERT INTO `designs` (`id`, `owner_id`, `title`, `description`, `category`, `visibility`, `status`, `allow_download`, `scheduled_at`, `published_at`, `comments_count`, `likes_count`, `saves_count`, `created_at`, `updated_at`, `kind`, `media_path`, `figma_url`, `allow_comments`) VALUES
(1, 1, 'Contoh Desain', 'Deskripsi singkat', 'desain', 'public', 'published', 0, NULL, '2025-11-07 08:54:01', 0, 0, 0, '2025-11-07 08:54:01', '2025-11-10 11:33:10', 'image', NULL, NULL, 1),
(4, 3, 'Poster uji coba', NULL, 'logo', 'public', 'draft', 1, NULL, NULL, 0, 0, 0, '2025-11-09 10:24:19', '2025-11-09 10:24:19', 'image', 'uploads/img_20251109_102419_4736bd6b.png', NULL, 1),
(6, 3, 'Poster uji coba', NULL, 'logo', 'public', 'published', 1, NULL, NULL, 0, 0, 0, '2025-11-09 14:36:52', '2025-11-09 14:36:52', 'image', 'uploads/img_20251109_143652_81671b8b.png', NULL, 1),
(8, 3, 'Leading figma', NULL, 'website', 'public', 'published', 0, NULL, '2025-11-10 11:17:01', 0, 0, 0, '2025-11-10 11:17:01', '2025-11-10 11:17:01', 'figma', NULL, 'https://www.figma.com/design/5pu74Gv9IsZZwb6KbYCeqB/Untitled?node-id=0-1&p=f&t=3SCywGE7RnPimXsX-0', 1),
(9, 4, 'Pahri Ganteng banget', 'Pahri Ganteng banget', 'website', 'public', 'published', 0, NULL, '2025-11-10 12:22:24', 1, 0, 0, '2025-11-10 12:22:24', '2025-11-10 12:32:47', 'figma', NULL, 'https://www.figma.com/design/7J8v6Bpam3bxduks7c16r8/Untitled?node-id=0-1&p=f&t=iG9U0mrBTtJC2gW4-0', 1),
(10, 2, 'Untitled', NULL, 'website', 'public', 'published', 0, NULL, '2025-11-10 18:51:12', 0, 0, 0, '2025-11-10 18:51:12', '2025-11-10 18:51:12', 'figma', NULL, 'https://www.figma.com/design/QJN7bf3AogNXhqCtKhieLb/70--Free-Footer-for-web-design-%7C-Components--Community-?m=auto&t=2RedWqTbkCpt3mu0-6', 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `design_images`
--

CREATE TABLE `design_images` (
  `id` int(11) NOT NULL,
  `design_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `position` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `mime` varchar(80) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `design_likes`
--

CREATE TABLE `design_likes` (
  `id` int(11) NOT NULL,
  `design_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `design_saves`
--

CREATE TABLE `design_saves` (
  `id` int(11) NOT NULL,
  `design_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `design_saves`
--

INSERT INTO `design_saves` (`id`, `design_id`, `user_id`, `created_at`) VALUES
(5, 9, 3, '2025-11-10 12:32:30');

-- --------------------------------------------------------

--
-- Struktur dari tabel `request_log`
--

CREATE TABLE `request_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `email`, `name`, `avatar`, `avatar_url`, `created_at`) VALUES
(1, 'demo@example.com', 'demo', NULL, NULL, '2025-11-07 08:54:01'),
(2, 'tuan@gmail.com', 'tuan', NULL, NULL, '2025-11-07 09:59:31'),
(3, 'tuan@example.com', 'Tuan', NULL, NULL, '2025-11-09 09:15:53'),
(4, 'tiunpam@gmai.com', 'tiunpam', NULL, NULL, '2025-11-10 12:14:06');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `designs`
--
ALTER TABLE `designs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `design_images`
--
ALTER TABLE `design_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `design_likes`
--
ALTER TABLE `design_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `design_saves`
--
ALTER TABLE `design_saves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `request_log`
--
ALTER TABLE `request_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
