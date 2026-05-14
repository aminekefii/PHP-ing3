-- TEK-UP Certified Students database
-- Import in phpMyAdmin: Import tab > Choose file > Go.

CREATE DATABASE IF NOT EXISTS `tekup`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `tekup`;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `email`      VARCHAR(190) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `firstname`  VARCHAR(100) DEFAULT NULL,
  `lastname`   VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed users. Passwords are plain text for simplicity, as agreed.
-- In production replace with password_hash() and verify with password_verify().
INSERT INTO `users` (`email`, `password`, `firstname`, `lastname`) VALUES
  ('user1@tek-up.de', '123456789', 'User', 'One'),
  ('user2@tek-up.de', '123456789', 'User', 'Two');

-- Profile table. One row per user. user_id doubles as the student ID
-- (rendered as TU-00001, TU-00002...) and is the foreign key to users.id.
DROP TABLE IF EXISTS `profiles`;
CREATE TABLE `profiles` (
  `user_id`    INT NOT NULL PRIMARY KEY,
  `phone`      VARCHAR(50)  DEFAULT NULL,
  `address`    VARCHAR(255) DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_profiles_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed empty profiles for the two users.
INSERT INTO `profiles` (`user_id`, `phone`, `address`) VALUES
  (1, NULL, NULL),
  (2, NULL, NULL);

-- Catalog of certifications offered (master list, shared across users).
DROP TABLE IF EXISTS `user_certifications`;
DROP TABLE IF EXISTS `certifications`;

CREATE TABLE `certifications` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `code`        VARCHAR(50)  NOT NULL UNIQUE,
  `name`        VARCHAR(255) NOT NULL,
  `provider`    VARCHAR(100) NOT NULL,
  `category`    ENUM('technical','linguistic','other') NOT NULL DEFAULT 'technical',
  `description` VARCHAR(500) DEFAULT NULL,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-user enrolment / progress / earned status.
CREATE TABLE `user_certifications` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`          INT NOT NULL,
  `certification_id` INT NOT NULL,
  `status`           ENUM('in_progress','earned') NOT NULL DEFAULT 'in_progress',
  `progress`         TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `enrolled_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `earned_at`        TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY `uq_user_cert` (`user_id`, `certification_id`),
  CONSTRAINT `fk_uc_user` FOREIGN KEY (`user_id`)          REFERENCES `users`(`id`)          ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_uc_cert` FOREIGN KEY (`certification_id`) REFERENCES `certifications`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catalog seed, based on the TEK-UP certifications poster.
INSERT INTO `certifications` (`code`, `name`, `provider`, `category`, `description`) VALUES
  ('CCNA',          'CCNA — Networking Associate',                'Cisco',             'technical',  'Routing, switching, network fundamentals and security basics.'),
  ('CCNP',          'CCNP — Networking Professional',             'Cisco',             'technical',  'Enterprise networking, advanced routing and infrastructure.'),
  ('CCIE',          'CCIE — Networking Expert',                   'Cisco',             'technical',  'Expert-level networking certification with hands-on lab exam.'),
  ('AWS-CLF',       'AWS Cloud Practitioner',                     'Amazon AWS',        'technical',  'Foundational AWS Cloud concepts and services.'),
  ('AWS-SAA',       'AWS Solutions Architect — Associate',        'Amazon AWS',        'technical',  'Design distributed systems on AWS at associate level.'),
  ('AWS-SAP',       'AWS Solutions Architect — Professional',     'Amazon AWS',        'technical',  'Design and deploy large-scale, fault-tolerant systems on AWS.'),
  ('AWS-DVA',       'AWS Developer — Associate',                  'Amazon AWS',        'technical',  'Develop, deploy and debug cloud-based applications on AWS.'),
  ('CEH',           'CEH — Certified Ethical Hacker',             'EC-Council',        'technical',  'Tools and methods of ethical hacking, real-world labs.'),
  ('CHFI',          'CHFI — Hacking Forensic Investigator',       'EC-Council',        'technical',  'Digital forensics, investigation and incident response.'),
  ('CND',           'CND — Certified Network Defender',           'EC-Council',        'technical',  'Defensive network security operations.'),
  ('RHCSA-EX200',   'RHCSA (EX-200)',                             'Red Hat',           'technical',  'Red Hat Certified System Administrator.'),
  ('RHCE-EX294',    'RHCE (EX-294)',                              'Red Hat',           'technical',  'Red Hat Certified Engineer with Ansible automation.'),
  ('RHCS-EX447',    'RHCS Ansible Best Practices (EX-447)',       'Red Hat',           'technical',  'Advanced Ansible automation with Red Hat.'),
  ('RHCS-EX358',    'RHCS Services Management (EX-358)',          'Red Hat',           'technical',  'Network services on Red Hat Enterprise Linux.'),
  ('eWPT',          'eWPT — Web Penetration Tester',              'INE',               'technical',  'Web application penetration testing certification.'),
  ('eCPPT',         'eCPPT — Professional Penetration Tester',    'INE',               'technical',  'Hands-on professional penetration tester certification.'),
  ('eMAPT',         'eMAPT — Mobile App Penetration Tester',      'INE',               'technical',  'Mobile application penetration testing.'),
  ('eSOC',          'eSOC — SOC Analyst',                         'INE',               'technical',  'Security Operations Center analyst certification.'),
  ('HCIA',          'HCIA — ICT Associate',                       'Huawei',            'technical',  'Huawei Certified ICT Associate.'),
  ('HCIP',          'HCIP — ICT Professional',                    'Huawei',            'technical',  'Huawei Certified ICT Professional.'),
  ('HCIE',          'HCIE — ICT Expert',                          'Huawei',            'technical',  'Huawei Certified ICT Expert.'),
  ('OCA-JAVA',      'Oracle Java — Associate (OCA)',              'Oracle',            'technical',  'Oracle Certified Associate in Java SE.'),
  ('OCP-JAVA',      'Oracle Java — Professional (OCP)',           'Oracle',            'technical',  'Oracle Certified Professional in Java SE.'),
  ('OCA-DB',        'Oracle Database — Associate',                'Oracle',            'technical',  'Oracle Database Administration at associate level.'),
  ('OCP-DB',        'Oracle Database — Professional',             'Oracle',            'technical',  'Oracle Database Administration at professional level.'),
  ('PCEP',          'PCEP — Entry-Level Python',                  'Python Institute',  'technical',  'Entry-level Python programming certification.'),
  ('PCAP',          'PCAP — Associate Python Programmer',         'Python Institute',  'technical',  'Associate-level Python programming certification.'),
  ('PCPP',          'PCPP — Professional Python Programmer',      'Python Institute',  'technical',  'Professional-level Python programming certification.'),
  ('MS-ITS',        'Microsoft IT Specialist',                    'Microsoft',         'technical',  'Foundational Microsoft IT Specialist track.'),
  ('AZ-900',        'Azure Fundamentals (AZ-900)',                'Microsoft',         'technical',  'Foundational knowledge of Microsoft Azure services.'),
  ('LFCS',          'LFCS — Linux Foundation Certified SysAdmin', 'Linux Foundation',  'technical',  'Linux Foundation Certified System Administrator.'),
  ('OSCP',          'OSCP — Penetration Tester',                  'Offensive Security','technical',  'Hands-on offensive security certification.'),
  ('IELTS',         'IELTS — English Proficiency',                'British Council',   'linguistic', 'International English Language Testing System.'),
  ('GOETHE-B1',     'Goethe-Zertifikat B1 — German',              'Goethe-Institut',   'linguistic', 'German language certification at B1 level.'),
  ('DELF-B1',       'DELF B1 — French',                           'France Éducation',  'linguistic', 'French language certification at B1 level.'),
  ('NSE-4',         'NSE 4 — Network Security Professional',      'Fortinet',          'other',      'Fortinet Network Security Professional certification.'),
  ('PECB-27001',    'ISO 27001 Lead Auditor',                     'PECB',              'other',      'Information security management system lead auditor.'),
  ('VCP-DCV',       'VCP-DCV — Data Center Virtualization',       'VMware',            'other',      'VMware Certified Professional, Data Center Virtualization.'),
  ('NVIDIA-CUDA',   'CUDA Developer',                             'NVIDIA',            'other',      'NVIDIA CUDA programming and parallel acceleration.'),
  ('ISTQB-FL',      'ISTQB Foundation Level',                     'ISTQB',             'other',      'Software testing foundation certification.');

-- ---------------------------------------------------------------
-- Course content: sections, videos, and per-user progress.
-- Added 2026-05-14.
-- ---------------------------------------------------------------

ALTER TABLE `certifications`
  ADD COLUMN IF NOT EXISTS `media_path` VARCHAR(255) NULL AFTER `description`;

DROP TABLE IF EXISTS `video_progress`;
DROP TABLE IF EXISTS `course_videos`;
DROP TABLE IF EXISTS `course_sections`;

CREATE TABLE `course_sections` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `certification_id` INT NOT NULL,
  `position`         SMALLINT NOT NULL,
  `title`            VARCHAR(255) NOT NULL,
  `folder`           VARCHAR(255) NOT NULL,
  UNIQUE KEY `uq_cert_pos` (`certification_id`, `position`),
  CONSTRAINT `fk_section_cert`
    FOREIGN KEY (`certification_id`) REFERENCES `certifications`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `course_videos` (
  `id`                INT AUTO_INCREMENT PRIMARY KEY,
  `section_id`        INT NOT NULL,
  `position`          SMALLINT NOT NULL,
  `title`             VARCHAR(255) NOT NULL,
  `filename`          VARCHAR(255) NOT NULL,
  `subtitle_filename` VARCHAR(255) NULL,
  `duration_seconds`  INT NULL,
  UNIQUE KEY `uq_section_pos` (`section_id`, `position`),
  CONSTRAINT `fk_video_section`
    FOREIGN KEY (`section_id`) REFERENCES `course_sections`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `video_progress` (
  `user_id`    INT NOT NULL,
  `video_id`   INT NOT NULL,
  `watched_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `video_id`),
  CONSTRAINT `fk_vp_user`  FOREIGN KEY (`user_id`)  REFERENCES `users`(`id`)         ON DELETE CASCADE,
  CONSTRAINT `fk_vp_video` FOREIGN KEY (`video_id`) REFERENCES `course_videos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `voucher_requests` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`          INT NOT NULL,
  `certification_id` INT NOT NULL,
  `status`           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `requested_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_vr_user_cert` (`user_id`, `certification_id`),
  CONSTRAINT `fk_vr_user` FOREIGN KEY (`user_id`)          REFERENCES `users`(`id`)          ON DELETE CASCADE,
  CONSTRAINT `fk_vr_cert` FOREIGN KEY (`certification_id`) REFERENCES `certifications`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
