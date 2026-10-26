
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `nom` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `type` enum('depense','revenu') NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_user_id_nom_type_unique` (`user_id`,`nom`,`type`),
  CONSTRAINT `categories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,2,'Alimentation',NULL,'depense','2026-10-26 13:39:24','2026-10-26 13:39:24'),(2,2,'Transport',NULL,'depense','2026-10-26 13:39:24','2026-10-26 13:39:24'),(3,2,'Logement',NULL,'depense','2026-10-26 13:39:24','2026-10-26 13:39:24'),(4,2,'Santé',NULL,'depense','2026-10-26 13:39:24','2026-10-26 13:39:24'),(5,2,'Éducation',NULL,'depense','2026-10-26 13:39:24','2026-10-26 13:39:24'),(6,2,'Facture d\'électricité',NULL,'depense','2026-10-26 13:39:24','2026-10-26 13:39:24'),(7,2,'Facture d\'eau',NULL,'depense','2026-10-26 13:39:24','2026-10-26 13:39:24'),(8,2,'Facture d\'internet',NULL,'depense','2026-10-26 13:39:24','2026-10-26 13:39:24'),(9,2,'Facture globale','Eau, électricité et internet réunis','depense','2026-10-26 13:39:24','2026-10-26 13:39:24'),(10,2,'Loisirs',NULL,'depense','2026-10-26 13:39:24','2026-10-26 13:39:24'),(11,2,'Salaire',NULL,'revenu','2026-10-26 13:39:24','2026-10-26 13:39:24'),(12,2,'Activité commerciale',NULL,'revenu','2026-10-26 13:39:24','2026-10-26 13:39:24'),(13,2,'Autres revenus',NULL,'revenu','2026-10-26 13:39:24','2026-10-26 13:39:24'),(14,3,'Alimentation',NULL,'depense','2026-10-26 13:39:25','2026-10-26 13:39:25'),(15,3,'Transport',NULL,'depense','2026-10-26 13:39:25','2026-10-26 13:39:25'),(16,3,'Logement',NULL,'depense','2026-10-26 13:39:25','2026-10-26 13:39:25'),(17,3,'Santé',NULL,'depense','2026-10-26 13:39:25','2026-10-26 13:39:25'),(18,3,'Éducation',NULL,'depense','2026-10-26 13:39:25','2026-10-26 13:39:25'),(19,3,'Facture d\'électricité',NULL,'depense','2026-10-26 13:39:25','2026-10-26 13:39:25'),(20,3,'Facture d\'eau',NULL,'depense','2026-10-26 13:39:25','2026-10-26 13:39:25'),(21,3,'Facture d\'internet',NULL,'depense','2026-10-26 13:39:25','2026-10-26 13:39:25'),(22,3,'Facture globale','Eau, électricité et internet réunis','depense','2026-10-26 13:39:25','2026-10-26 13:39:25'),(23,3,'Loisirs',NULL,'depense','2026-10-26 13:39:25','2026-10-26 13:39:25'),(24,3,'Salaire',NULL,'revenu','2026-10-26 13:39:25','2026-10-26 13:39:25'),(25,3,'Activité commerciale',NULL,'revenu','2026-10-26 13:39:25','2026-10-26 13:39:25'),(26,3,'Autres revenus',NULL,'revenu','2026-10-26 13:39:25','2026-10-26 13:39:25');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
DROP TABLE IF EXISTS `depenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `depenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `categorie_id` bigint(20) unsigned NOT NULL,
  `montant` decimal(12,2) NOT NULL,
  `date` date NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `depenses_categorie_id_foreign` (`categorie_id`),
  KEY `depenses_user_id_date_index` (`user_id`,`date`),
  CONSTRAINT `depenses_categorie_id_foreign` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `depenses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `depenses` DISABLE KEYS */;
INSERT INTO `depenses` VALUES (1,2,3,75000.00,'2026-10-05','[TEST] Loyer','2026-10-26 13:39:25','2026-10-26 13:39:25'),(2,2,1,19500.00,'2026-10-03','[TEST] Courses au marché','2026-10-26 13:39:25','2026-10-26 13:39:25'),(3,2,1,22500.00,'2026-10-10','[TEST] Courses supermarché','2026-10-26 13:39:25','2026-10-26 13:39:25'),(4,2,1,17000.00,'2026-10-17','[TEST] Courses au marché','2026-10-26 13:39:25','2026-10-26 13:39:25'),(5,2,1,23000.00,'2026-10-24','[TEST] Courses supermarché','2026-10-26 13:39:25','2026-10-26 13:39:25'),(6,2,2,6000.00,'2026-10-07','[TEST] Transport en commun','2026-10-26 13:39:25','2026-10-26 13:39:25'),(7,2,2,7000.00,'2026-10-21','[TEST] Taxi','2026-10-26 13:39:25','2026-10-26 13:39:25'),(8,2,6,23000.00,'2026-10-12','[TEST] Facture électricité','2026-10-26 13:39:25','2026-10-26 13:39:25'),(9,2,8,17000.00,'2026-10-14','[TEST] Abonnement internet','2026-10-26 13:39:25','2026-10-26 13:39:25'),(10,2,10,13500.00,'2026-10-18','[TEST] Sortie au restaurant','2026-10-26 13:39:25','2026-10-26 13:39:25'),(11,2,3,75000.00,'2026-09-05','[TEST] Loyer','2026-10-26 13:39:25','2026-10-26 13:39:25'),(12,2,1,18000.00,'2026-09-03','[TEST] Courses au marché','2026-10-26 13:39:25','2026-10-26 13:39:25'),(13,2,1,23500.00,'2026-09-10','[TEST] Courses supermarché','2026-10-26 13:39:25','2026-10-26 13:39:25'),(14,2,1,18000.00,'2026-09-17','[TEST] Courses au marché','2026-10-26 13:39:25','2026-10-26 13:39:25'),(15,2,1,21500.00,'2026-09-24','[TEST] Courses supermarché','2026-10-26 13:39:25','2026-10-26 13:39:25'),(16,2,2,7000.00,'2026-09-07','[TEST] Transport en commun','2026-10-26 13:39:25','2026-10-26 13:39:25'),(17,2,2,8000.00,'2026-09-21','[TEST] Taxi','2026-10-26 13:39:25','2026-10-26 13:39:25'),(18,2,6,24000.00,'2026-09-12','[TEST] Facture électricité','2026-10-26 13:39:25','2026-10-26 13:39:25'),(19,2,8,15500.00,'2026-09-14','[TEST] Abonnement internet','2026-10-26 13:39:25','2026-10-26 13:39:25'),(20,2,10,12000.00,'2026-09-18','[TEST] Sortie au restaurant','2026-10-26 13:39:25','2026-10-26 13:39:25'),(21,2,4,9000.00,'2026-09-09','[TEST] Pharmacie','2026-10-26 13:39:25','2026-10-26 13:39:25'),(22,2,5,22000.00,'2026-09-02','[TEST] Frais de scolarité','2026-10-26 13:39:25','2026-10-26 13:39:25'),(23,2,3,75000.00,'2026-08-05','[TEST] Loyer','2026-10-26 13:39:25','2026-10-26 13:39:25'),(24,2,1,19000.00,'2026-08-03','[TEST] Courses au marché','2026-10-26 13:39:25','2026-10-26 13:39:25'),(25,2,1,24500.00,'2026-08-10','[TEST] Courses supermarché','2026-10-26 13:39:25','2026-10-26 13:39:25'),(26,2,1,16500.00,'2026-08-17','[TEST] Courses au marché','2026-10-26 13:39:25','2026-10-26 13:39:25'),(27,2,1,22500.00,'2026-08-24','[TEST] Courses supermarché','2026-10-26 13:39:25','2026-10-26 13:39:25'),(28,2,2,5500.00,'2026-08-07','[TEST] Transport en commun','2026-10-26 13:39:25','2026-10-26 13:39:25'),(29,2,2,6500.00,'2026-08-21','[TEST] Taxi','2026-10-26 13:39:25','2026-10-26 13:39:25'),(30,2,6,22500.00,'2026-08-12','[TEST] Facture électricité','2026-10-26 13:39:25','2026-10-26 13:39:25'),(31,2,8,16500.00,'2026-08-14','[TEST] Abonnement internet','2026-10-26 13:39:25','2026-10-26 13:39:25'),(32,2,10,13000.00,'2026-08-18','[TEST] Sortie au restaurant','2026-10-26 13:39:25','2026-10-26 13:39:25'),(33,2,3,75000.00,'2026-07-05','[TEST] Loyer','2026-10-26 13:39:25','2026-10-26 13:39:25'),(34,2,1,20000.00,'2026-07-03','[TEST] Courses au marché','2026-10-26 13:39:25','2026-10-26 13:39:25'),(35,2,1,23000.00,'2026-07-10','[TEST] Courses supermarché','2026-10-26 13:39:25','2026-10-26 13:39:25'),(36,2,1,17500.00,'2026-07-17','[TEST] Courses au marché','2026-10-26 13:39:25','2026-10-26 13:39:25'),(37,2,1,21000.00,'2026-07-24','[TEST] Courses supermarché','2026-10-26 13:39:25','2026-10-26 13:39:25'),(38,2,2,6500.00,'2026-07-07','[TEST] Transport en commun','2026-10-26 13:39:25','2026-10-26 13:39:25'),(39,2,2,7500.00,'2026-07-21','[TEST] Taxi','2026-10-26 13:39:25','2026-10-26 13:39:25'),(40,2,6,23500.00,'2026-07-12','[TEST] Facture électricité','2026-10-26 13:39:25','2026-10-26 13:39:25'),(41,2,8,15000.00,'2026-07-14','[TEST] Abonnement internet','2026-10-26 13:39:25','2026-10-26 13:39:25'),(42,2,10,14000.00,'2026-07-18','[TEST] Sortie au restaurant','2026-10-26 13:39:25','2026-10-26 13:39:25'),(43,2,5,21500.00,'2026-07-02','[TEST] Frais de scolarité','2026-10-26 13:39:25','2026-10-26 13:39:25'),(44,2,3,75000.00,'2026-06-05','[TEST] Loyer','2026-10-26 13:39:25','2026-10-26 13:39:25'),(45,2,1,18500.00,'2026-06-03','[TEST] Courses au marché','2026-10-26 13:39:25','2026-10-26 13:39:25'),(46,2,1,24000.00,'2026-06-10','[TEST] Courses supermarché','2026-10-26 13:39:25','2026-10-26 13:39:25'),(47,2,1,16000.00,'2026-06-17','[TEST] Courses au marché','2026-10-26 13:39:25','2026-10-26 13:39:25'),(48,2,1,22000.00,'2026-06-24','[TEST] Courses supermarché','2026-10-26 13:39:25','2026-10-26 13:39:25'),(49,2,2,5000.00,'2026-06-07','[TEST] Transport en commun','2026-10-26 13:39:25','2026-10-26 13:39:25'),(50,2,2,8500.00,'2026-06-21','[TEST] Taxi','2026-10-26 13:39:25','2026-10-26 13:39:25'),(51,2,6,22000.00,'2026-06-12','[TEST] Facture électricité','2026-10-26 13:39:25','2026-10-26 13:39:25'),(52,2,8,16000.00,'2026-06-14','[TEST] Abonnement internet','2026-10-26 13:39:25','2026-10-26 13:39:25'),(53,2,10,12500.00,'2026-06-18','[TEST] Sortie au restaurant','2026-10-26 13:39:25','2026-10-26 13:39:25'),(54,2,4,9500.00,'2026-06-09','[TEST] Pharmacie','2026-10-26 13:39:25','2026-10-26 13:39:25'),(55,2,3,75000.00,'2026-05-05','[TEST] Loyer','2026-10-26 13:39:25','2026-10-26 13:39:25'),(56,2,1,19500.00,'2026-05-03','[TEST] Courses au marché','2026-10-26 13:39:25','2026-10-26 13:39:25'),(57,2,1,22500.00,'2026-05-10','[TEST] Courses supermarché','2026-10-26 13:39:25','2026-10-26 13:39:25'),(58,2,1,17000.00,'2026-05-17','[TEST] Courses au marché','2026-10-26 13:39:25','2026-10-26 13:39:25'),(59,2,1,23000.00,'2026-05-24','[TEST] Courses supermarché','2026-10-26 13:39:25','2026-10-26 13:39:25'),(60,2,2,6000.00,'2026-05-07','[TEST] Transport en commun','2026-10-26 13:39:25','2026-10-26 13:39:25'),(61,2,2,7000.00,'2026-05-21','[TEST] Taxi','2026-10-26 13:39:25','2026-10-26 13:39:25'),(62,2,6,23000.00,'2026-05-12','[TEST] Facture électricité','2026-10-26 13:39:25','2026-10-26 13:39:25'),(63,2,8,17000.00,'2026-05-14','[TEST] Abonnement internet','2026-10-26 13:39:25','2026-10-26 13:39:25'),(64,2,10,13500.00,'2026-05-18','[TEST] Sortie au restaurant','2026-10-26 13:39:25','2026-10-26 13:39:25'),(65,2,5,21000.00,'2026-05-02','[TEST] Frais de scolarité','2026-10-26 13:39:25','2026-10-26 13:39:25'),(66,3,16,60000.00,'2026-10-04','[TEST] Loyer','2026-10-26 13:39:25','2026-10-26 13:39:25'),(67,3,14,24500.00,'2026-10-06','[TEST] Courses','2026-10-26 13:39:25','2026-10-26 13:39:25'),(68,3,14,21500.00,'2026-10-19','[TEST] Courses','2026-10-26 13:39:25','2026-10-26 13:39:25'),(69,3,15,10500.00,'2026-10-08','[TEST] Transport en commun','2026-10-26 13:39:25','2026-10-26 13:39:25'),(70,3,22,19500.00,'2026-10-13','[TEST] Facture électricité et eau','2026-10-26 13:39:25','2026-10-26 13:39:25'),(71,3,23,8500.00,'2026-10-22','[TEST] Cinéma et sorties','2026-10-26 13:39:25','2026-10-26 13:39:25'),(72,3,16,60000.00,'2026-09-04','[TEST] Loyer','2026-10-26 13:39:25','2026-10-26 13:39:25'),(73,3,14,25500.00,'2026-09-06','[TEST] Courses','2026-10-26 13:39:25','2026-10-26 13:39:25'),(74,3,14,20000.00,'2026-09-19','[TEST] Courses','2026-10-26 13:39:25','2026-10-26 13:39:25'),(75,3,15,9000.00,'2026-09-08','[TEST] Transport en commun','2026-10-26 13:39:25','2026-10-26 13:39:25'),(76,3,22,18000.00,'2026-09-13','[TEST] Facture électricité et eau','2026-10-26 13:39:25','2026-10-26 13:39:25'),(77,3,16,60000.00,'2026-08-04','[TEST] Loyer','2026-10-26 13:39:25','2026-10-26 13:39:25'),(78,3,14,24000.00,'2026-08-06','[TEST] Courses','2026-10-26 13:39:25','2026-10-26 13:39:25'),(79,3,14,21000.00,'2026-08-19','[TEST] Courses','2026-10-26 13:39:25','2026-10-26 13:39:25'),(80,3,15,10000.00,'2026-08-08','[TEST] Transport en commun','2026-10-26 13:39:25','2026-10-26 13:39:25'),(81,3,22,19000.00,'2026-08-13','[TEST] Facture électricité et eau','2026-10-26 13:39:25','2026-10-26 13:39:25'),(82,3,23,8000.00,'2026-08-22','[TEST] Cinéma et sorties','2026-10-26 13:39:25','2026-10-26 13:39:25'),(83,3,17,6000.00,'2026-08-11','[TEST] Consultation','2026-10-26 13:39:25','2026-10-26 13:39:25'),(84,3,16,60000.00,'2026-07-04','[TEST] Loyer','2026-10-26 13:39:25','2026-10-26 13:39:25'),(85,3,14,25000.00,'2026-07-06','[TEST] Courses','2026-10-26 13:39:25','2026-10-26 13:39:25'),(86,3,14,19500.00,'2026-07-19','[TEST] Courses','2026-10-26 13:39:25','2026-10-26 13:39:25'),(87,3,15,11000.00,'2026-07-08','[TEST] Transport en commun','2026-10-26 13:39:25','2026-10-26 13:39:25'),(88,3,22,20000.00,'2026-07-13','[TEST] Facture électricité et eau','2026-10-26 13:39:25','2026-10-26 13:39:25'),(89,3,16,60000.00,'2026-06-04','[TEST] Loyer','2026-10-26 13:39:25','2026-10-26 13:39:25'),(90,3,14,26000.00,'2026-06-06','[TEST] Courses','2026-10-26 13:39:25','2026-10-26 13:39:25'),(91,3,14,20500.00,'2026-06-19','[TEST] Courses','2026-10-26 13:39:25','2026-10-26 13:39:25'),(92,3,15,9500.00,'2026-06-08','[TEST] Transport en commun','2026-10-26 13:39:25','2026-10-26 13:39:25'),(93,3,22,18500.00,'2026-06-13','[TEST] Facture électricité et eau','2026-10-26 13:39:25','2026-10-26 13:39:25'),(94,3,23,7500.00,'2026-06-22','[TEST] Cinéma et sorties','2026-10-26 13:39:25','2026-10-26 13:39:25'),(95,3,16,60000.00,'2026-05-04','[TEST] Loyer','2026-10-26 13:39:25','2026-10-26 13:39:25'),(96,3,14,24500.00,'2026-05-06','[TEST] Courses','2026-10-26 13:39:25','2026-10-26 13:39:25'),(97,3,14,21500.00,'2026-05-19','[TEST] Courses','2026-10-26 13:39:25','2026-10-26 13:39:25'),(98,3,15,10500.00,'2026-05-08','[TEST] Transport en commun','2026-10-26 13:39:25','2026-10-26 13:39:25'),(99,3,22,19500.00,'2026-05-13','[TEST] Facture électricité et eau','2026-10-26 13:39:25','2026-10-26 13:39:25');
/*!40000 ALTER TABLE `depenses` ENABLE KEYS */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2026_10_12_163832_create_users_table',1),(2,'2026_10_12_164703_create_categories_table',1),(3,'2026_10_12_164706_create_depenses_table',1),(4,'2026_10_12_164708_create_revenus_table',1),(5,'2026_10_16_090000_ajouter_categories_factures_aux_comptes_existants',1),(6,'2026_10_16_100000_remplacer_la_categorie_factures_par_facture_globale',1),(7,'2026_10_21_090000_ajouter_suivi_de_connexion_aux_utilisateurs',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
DROP TABLE IF EXISTS `revenus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `revenus` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `categorie_id` bigint(20) unsigned NOT NULL,
  `montant` decimal(12,2) NOT NULL,
  `date` date NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `revenus_categorie_id_foreign` (`categorie_id`),
  KEY `revenus_user_id_date_index` (`user_id`,`date`),
  CONSTRAINT `revenus_categorie_id_foreign` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `revenus_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `revenus` DISABLE KEYS */;
INSERT INTO `revenus` VALUES (1,2,11,250000.00,'2026-10-01','[TEST] Salaire','2026-10-26 13:39:25','2026-10-26 13:39:25'),(2,2,12,45000.00,'2026-10-20','[TEST] Vente de tissus','2026-10-26 13:39:25','2026-10-26 13:39:25'),(3,2,11,250000.00,'2026-09-01','[TEST] Salaire','2026-10-26 13:39:25','2026-10-26 13:39:25'),(4,2,11,250000.00,'2026-08-01','[TEST] Salaire','2026-10-26 13:39:25','2026-10-26 13:39:25'),(5,2,12,47000.00,'2026-08-20','[TEST] Vente de tissus','2026-10-26 13:39:25','2026-10-26 13:39:25'),(6,2,11,250000.00,'2026-07-01','[TEST] Salaire','2026-10-26 13:39:25','2026-10-26 13:39:25'),(7,2,13,26500.00,'2026-07-12','[TEST] Remboursement','2026-10-26 13:39:25','2026-10-26 13:39:25'),(8,2,11,250000.00,'2026-06-01','[TEST] Salaire','2026-10-26 13:39:25','2026-10-26 13:39:25'),(9,2,12,46500.00,'2026-06-20','[TEST] Vente de tissus','2026-10-26 13:39:25','2026-10-26 13:39:25'),(10,2,11,250000.00,'2026-05-01','[TEST] Salaire','2026-10-26 13:39:25','2026-10-26 13:39:25'),(11,3,24,180000.00,'2026-10-02','[TEST] Salaire','2026-10-26 13:39:25','2026-10-26 13:39:25'),(12,3,25,30000.00,'2026-10-15','[TEST] Petite vente','2026-10-26 13:39:25','2026-10-26 13:39:25'),(13,3,24,180000.00,'2026-09-02','[TEST] Salaire','2026-10-26 13:39:25','2026-10-26 13:39:25'),(14,3,24,180000.00,'2026-08-02','[TEST] Salaire','2026-10-26 13:39:25','2026-10-26 13:39:25'),(15,3,24,180000.00,'2026-07-02','[TEST] Salaire','2026-10-26 13:39:25','2026-10-26 13:39:25'),(16,3,25,30500.00,'2026-07-15','[TEST] Petite vente','2026-10-26 13:39:25','2026-10-26 13:39:25'),(17,3,24,180000.00,'2026-06-02','[TEST] Salaire','2026-10-26 13:39:25','2026-10-26 13:39:25'),(18,3,24,180000.00,'2026-05-02','[TEST] Salaire','2026-10-26 13:39:25','2026-10-26 13:39:25');
/*!40000 ALTER TABLE `revenus` ENABLE KEYS */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('utilisateur','admin') NOT NULL DEFAULT 'utilisateur',
  `statut` enum('actif','suspendu') NOT NULL DEFAULT 'actif',
  `derniere_activite` timestamp NULL DEFAULT NULL,
  `deconnecte_le` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Admin','SmartBudget','admin@smartbudget.sn','$2y$12$ZAhHH1PsKUDLPn0y9Ph.SO2POTAPJmLNy3BXbB.GAJExs1x4ePCtq','admin','actif',NULL,NULL,NULL,'2026-10-26 13:39:24','2026-10-26 13:39:24'),(2,'Diop','Awa','awa@smartbudget.sn','$2y$12$ucZfC1/rg1VSbB4Y93HIN.Fe4ELNCBgK3jwzLyGLw23o5yK0tRguC','utilisateur','actif',NULL,NULL,NULL,'2026-10-26 13:39:24','2026-10-26 13:39:24'),(3,'Ndiaye','Moussa','moussa@smartbudget.sn','$2y$12$poycq3WtcXzIdmWI/vscMu77VDhv7gkU4pBHC91YX4Eh9jojKPlWy','utilisateur','actif',NULL,NULL,NULL,'2026-10-26 13:39:25','2026-10-26 13:39:25');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

