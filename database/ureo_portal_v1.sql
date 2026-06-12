-- MySQL dump 10.13  Distrib 8.0.45, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: ureo_portal
-- ------------------------------------------------------
-- Server version	8.0.45

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `ai_classifications`
--

DROP TABLE IF EXISTS `ai_classifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_classifications` (
  `classification_id` int NOT NULL AUTO_INCREMENT,
  `queue_number` varchar(50) NOT NULL,
  `predicted_categories` json NOT NULL,
  `predicted_primary` varchar(100) DEFAULT NULL,
  `confidence_level` enum('high','moderate','low') DEFAULT 'moderate',
  `max_score` decimal(5,4) DEFAULT NULL,
  `all_scores` json DEFAULT NULL,
  `reasoning` text,
  `similar_past_cases` json DEFAULT NULL,
  `learning_stats` json DEFAULT NULL,
  `staff_verified` tinyint(1) DEFAULT '0',
  `staff_verified_by` int DEFAULT NULL,
  `staff_corrected_categories` json DEFAULT NULL,
  `staff_feedback` text,
  `verified_at` datetime DEFAULT NULL,
  `section_c_text` text,
  `processed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`classification_id`),
  KEY `staff_verified_by` (`staff_verified_by`),
  KEY `idx_queue` (`queue_number`),
  KEY `idx_verified` (`staff_verified`),
  CONSTRAINT `ai_classifications_ibfk_1` FOREIGN KEY (`queue_number`) REFERENCES `applications` (`queue_number`) ON DELETE CASCADE,
  CONSTRAINT `ai_classifications_ibfk_2` FOREIGN KEY (`staff_verified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `applications`
--

DROP TABLE IF EXISTS `applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `applications` (
  `queue_number` varchar(20) NOT NULL,
  `applicant_email` varchar(100) NOT NULL,
  `applicant_name` varchar(100) DEFAULT NULL,
  `applicant_type` enum('student','researcher','faculty') NOT NULL,
  `research_title` varchar(255) DEFAULT NULL,
  `submission_timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `current_status` varchar(50) NOT NULL DEFAULT 'INTENT_RECEIVED',
  `category` enum('exempt','expedited','full') DEFAULT NULL,
  `urec_committee_id` int DEFAULT NULL,
  `urec_reviewed_by` int DEFAULT NULL,
  `urec_review_notes` text,
  `urec_decision` enum('pending','approved','revisions_required','rejected') DEFAULT 'pending',
  `urec_decision_date` timestamp NULL DEFAULT NULL,
  `forwarded_to_urec_at` timestamp NULL DEFAULT NULL,
  `forwarded_by_staff` int DEFAULT NULL,
  `assigned_staff_id` int DEFAULT NULL,
  `urec_assigned_at` timestamp NULL DEFAULT NULL,
  `has_additional_requirements` tinyint(1) DEFAULT '0',
  `completion_attempts` int DEFAULT '0',
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`queue_number`),
  KEY `idx_status` (`current_status`),
  KEY `idx_email` (`applicant_email`),
  KEY `idx_assigned_staff` (`assigned_staff_id`),
  KEY `urec_reviewed_by` (`urec_reviewed_by`),
  KEY `forwarded_by_staff` (`forwarded_by_staff`),
  KEY `idx_urec_committee` (`urec_committee_id`),
  KEY `idx_urec_decision` (`urec_decision`),
  KEY `idx_forwarded_to_urec` (`forwarded_to_urec_at`),
  CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`assigned_staff_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`urec_committee_id`) REFERENCES `urec_committees` (`committee_id`) ON DELETE SET NULL,
  CONSTRAINT `applications_ibfk_3` FOREIGN KEY (`urec_reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `applications_ibfk_4` FOREIGN KEY (`forwarded_by_staff`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `category_forms`
--

DROP TABLE IF EXISTS `category_forms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `category_forms` (
  `id` int NOT NULL AUTO_INCREMENT,
  `queue_number` varchar(20) NOT NULL,
  `category` varchar(50) NOT NULL,
  `review_type` enum('exempt','expedited','full') NOT NULL,
  `form_data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_queue` (`queue_number`),
  CONSTRAINT `category_forms_ibfk_1` FOREIGN KEY (`queue_number`) REFERENCES `applications` (`queue_number`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents` (
  `document_id` int NOT NULL AUTO_INCREMENT,
  `queue_number` varchar(20) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int DEFAULT NULL,
  `upload_timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `validation_status` enum('pending','validated','rejected') DEFAULT 'pending',
  `validation_notes` text,
  PRIMARY KEY (`document_id`),
  KEY `idx_queue` (`queue_number`),
  KEY `idx_type` (`document_type`),
  CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`queue_number`) REFERENCES `applications` (`queue_number`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `email_logs`
--

DROP TABLE IF EXISTS `email_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_logs` (
  `email_id` int NOT NULL AUTO_INCREMENT,
  `queue_number` varchar(20) DEFAULT NULL,
  `recipient_email` varchar(100) NOT NULL,
  `email_type` varchar(50) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body_html` text,
  `sent_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('sent','failed','pending') DEFAULT 'pending',
  `error_message` text,
  PRIMARY KEY (`email_id`),
  KEY `idx_queue` (`queue_number`),
  KEY `idx_status` (`status`),
  CONSTRAINT `email_logs_ibfk_1` FOREIGN KEY (`queue_number`) REFERENCES `applications` (`queue_number`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `email_templates`
--

DROP TABLE IF EXISTS `email_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_templates` (
  `template_id` int NOT NULL AUTO_INCREMENT,
  `template_code` varchar(50) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `description` text,
  `category` varchar(50) NOT NULL DEFAULT 'general',
  `placeholders` text COMMENT 'JSON array of available placeholders',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`template_id`),
  UNIQUE KEY `template_code` (`template_code`),
  KEY `idx_code` (`template_code`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `fillable_forms`
--

DROP TABLE IF EXISTS `fillable_forms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fillable_forms` (
  `form_id` int NOT NULL AUTO_INCREMENT,
  `queue_number` varchar(20) NOT NULL,
  `form_type` varchar(50) NOT NULL,
  `form_data` json DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `file_generated` tinyint(1) DEFAULT '1',
  `file_uploaded` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`form_id`),
  UNIQUE KEY `unique_form` (`queue_number`,`form_type`),
  KEY `idx_queue` (`queue_number`),
  CONSTRAINT `fillable_forms_ibfk_1` FOREIGN KEY (`queue_number`) REFERENCES `applications` (`queue_number`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `message_id` int NOT NULL AUTO_INCREMENT,
  `queue_number` varchar(20) NOT NULL,
  `sender_type` enum('applicant','staff','system') NOT NULL,
  `sender_id` int DEFAULT NULL,
  `message_content` text NOT NULL,
  `attachment_path` varchar(500) DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `read_status` tinyint(1) DEFAULT '0',
  `read_at` timestamp NULL DEFAULT NULL,
  `read_by` int DEFAULT NULL,
  PRIMARY KEY (`message_id`),
  KEY `sender_id` (`sender_id`),
  KEY `read_by` (`read_by`),
  KEY `idx_queue` (`queue_number`),
  KEY `idx_read` (`read_status`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`queue_number`) REFERENCES `applications` (`queue_number`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`read_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `otp_sessions`
--

DROP TABLE IF EXISTS `otp_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `otp_sessions` (
  `session_id` int NOT NULL AUTO_INCREMENT,
  `queue_number` varchar(20) NOT NULL,
  `otp_code` varchar(6) NOT NULL,
  `generated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  `verified` tinyint(1) DEFAULT '0',
  `attempts` int DEFAULT '0',
  `ip_address` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`session_id`),
  KEY `idx_queue` (`queue_number`),
  KEY `idx_otp` (`otp_code`),
  CONSTRAINT `otp_sessions_ibfk_1` FOREIGN KEY (`queue_number`) REFERENCES `applications` (`queue_number`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `required_documents`
--

DROP TABLE IF EXISTS `required_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `required_documents` (
  `requirement_id` int NOT NULL AUTO_INCREMENT,
  `document_type` varchar(100) NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `description` text,
  `is_conditional` tinyint(1) DEFAULT '0',
  `conditional_field` varchar(100) DEFAULT NULL,
  `file_formats` varchar(100) DEFAULT 'pdf,doc,docx',
  `mandatory` tinyint(1) DEFAULT '1',
  `display_order` int DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`requirement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `staff_logs`
--

DROP TABLE IF EXISTS `staff_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `queue_number` varchar(20) DEFAULT NULL,
  `action_type` enum('opened_message','sent_reply','approved','rejected','viewed_application','downloaded_document','reclassified_ai','ai_feedback','other') NOT NULL,
  `action_details` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_staff` (`staff_id`),
  KEY `idx_queue` (`queue_number`),
  KEY `idx_timestamp` (`timestamp`),
  CONSTRAINT `staff_logs_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `staff_logs_ibfk_2` FOREIGN KEY (`queue_number`) REFERENCES `applications` (`queue_number`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `status_history`
--

DROP TABLE IF EXISTS `status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `status_history` (
  `history_id` int NOT NULL AUTO_INCREMENT,
  `queue_number` varchar(20) NOT NULL,
  `previous_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by` int DEFAULT NULL,
  `urec_committee_id` int DEFAULT NULL,
  `urec_review_stage` varchar(50) DEFAULT NULL,
  `changed_by_type` enum('system','staff','admin') NOT NULL,
  `notes` text,
  `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `changed_by` (`changed_by`),
  KEY `idx_queue` (`queue_number`),
  KEY `idx_urec_committee_history` (`urec_committee_id`),
  CONSTRAINT `status_history_ibfk_1` FOREIGN KEY (`queue_number`) REFERENCES `applications` (`queue_number`) ON DELETE CASCADE,
  CONSTRAINT `status_history_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `status_history_ibfk_3` FOREIGN KEY (`urec_committee_id`) REFERENCES `urec_committees` (`committee_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `system_documents`
--

DROP TABLE IF EXISTS `system_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_documents` (
  `system_doc_id` int NOT NULL AUTO_INCREMENT,
  `queue_number` varchar(50) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `document_path` varchar(500) NOT NULL,
  `document_type` enum('template','guideline','reference') NOT NULL,
  `provided_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`system_doc_id`),
  KEY `idx_queue_number` (`queue_number`),
  CONSTRAINT `system_documents_ibfk_1` FOREIGN KEY (`queue_number`) REFERENCES `applications` (`queue_number`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `system_messages`
--

DROP TABLE IF EXISTS `system_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_messages` (
  `message_id` int NOT NULL AUTO_INCREMENT,
  `queue_number` varchar(50) NOT NULL,
  `message_type` enum('acknowledgment','requirement','update','approval','rejection','certificate') NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message_body` text NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(1) DEFAULT '0',
  `read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`message_id`),
  KEY `idx_queue_number` (`queue_number`),
  KEY `idx_message_type` (`message_type`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `system_messages_ibfk_1` FOREIGN KEY (`queue_number`) REFERENCES `applications` (`queue_number`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_settings` (
  `setting_id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `description` text,
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urec_activity_log`
--

DROP TABLE IF EXISTS `urec_activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urec_activity_log` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `queue_number` varchar(20) DEFAULT NULL,
  `activity_type` varchar(50) NOT NULL,
  `activity_description` text,
  `committee_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_user_activity` (`user_id`),
  KEY `idx_queue_activity` (`queue_number`),
  KEY `idx_committee_activity` (`committee_id`),
  KEY `idx_activity_type` (`activity_type`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `urec_activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `urec_activity_log_ibfk_2` FOREIGN KEY (`committee_id`) REFERENCES `urec_committees` (`committee_id`) ON DELETE SET NULL,
  CONSTRAINT `urec_activity_log_ibfk_3` FOREIGN KEY (`queue_number`) REFERENCES `applications` (`queue_number`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urec_assignments`
--

DROP TABLE IF EXISTS `urec_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urec_assignments` (
  `assignment_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `committee_id` int NOT NULL,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `assigned_by` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`assignment_id`),
  KEY `user_id` (`user_id`),
  KEY `committee_id` (`committee_id`),
  CONSTRAINT `urec_assignments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `urec_assignments_ibfk_2` FOREIGN KEY (`committee_id`) REFERENCES `urec_committees` (`committee_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urec_committees`
--

DROP TABLE IF EXISTS `urec_committees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urec_committees` (
  `committee_id` int NOT NULL AUTO_INCREMENT,
  `committee_name` varchar(100) NOT NULL,
  `committee_code` varchar(20) NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`committee_id`),
  UNIQUE KEY `committee_name` (`committee_name`),
  UNIQUE KEY `committee_code` (`committee_code`),
  KEY `idx_committee_code` (`committee_code`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `urec_notification_preferences`
--

DROP TABLE IF EXISTS `urec_notification_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `urec_notification_preferences` (
  `preference_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `committee_id` int NOT NULL,
  `notification_type` varchar(50) NOT NULL,
  `is_enabled` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`preference_id`),
  UNIQUE KEY `unique_user_committee_notification` (`user_id`,`committee_id`,`notification_type`),
  KEY `idx_user_preferences` (`user_id`),
  KEY `idx_committee_preferences` (`committee_id`),
  CONSTRAINT `urec_notification_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `urec_notification_preferences_ibfk_2` FOREIGN KEY (`committee_id`) REFERENCES `urec_committees` (`committee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `user_role` enum('admin','staff','urec') DEFAULT 'staff',
  `committee_designation` varchar(50) DEFAULT NULL,
  `committee_id` int DEFAULT NULL,
  `role` enum('staff','admin') NOT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `active_status` tinyint(1) DEFAULT '1',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_username` (`username`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'ureo_portal'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-08 10:26:02
