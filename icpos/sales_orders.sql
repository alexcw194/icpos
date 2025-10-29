-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: localhost    Database: icpos
-- ------------------------------------------------------
-- Server version	8.4.3

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
-- Table structure for table `sales_orders`
--

DROP TABLE IF EXISTS `sales_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `quotation_id` bigint unsigned DEFAULT NULL,
  `sales_user_id` bigint unsigned DEFAULT NULL,
  `so_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_date` date NOT NULL,
  `customer_po_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_po_date` date NOT NULL,
  `deadline` date DEFAULT NULL,
  `ship_to` text COLLATE utf8mb4_unicode_ci,
  `bill_to` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `discount_mode` enum('total','per_item') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'total',
  `lines_subtotal` decimal(18,2) NOT NULL DEFAULT '0.00',
  `total_discount_type` enum('amount','percent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'amount',
  `total_discount_value` decimal(18,2) NOT NULL DEFAULT '0.00',
  `total_discount_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `taxable_base` decimal(18,2) NOT NULL DEFAULT '0.00',
  `tax_percent` decimal(5,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(18,2) NOT NULL DEFAULT '0.00',
  `total` decimal(18,2) NOT NULL DEFAULT '0.00',
  `npwp_required` tinyint(1) NOT NULL DEFAULT '0',
  `npwp_status` enum('ok','missing') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'missing',
  `tax_npwp_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_npwp_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_npwp_address` text COLLATE utf8mb4_unicode_ci,
  `status` enum('open','partial_delivered','delivered','invoiced','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_orders_so_number_unique` (`so_number`),
  KEY `sales_orders_company_id_foreign` (`company_id`),
  KEY `sales_orders_customer_id_foreign` (`customer_id`),
  KEY `sales_orders_quotation_id_foreign` (`quotation_id`),
  KEY `sales_orders_sales_user_id_foreign` (`sales_user_id`),
  CONSTRAINT `sales_orders_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `sales_orders_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `sales_orders_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_orders_sales_user_id_foreign` FOREIGN KEY (`sales_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales_orders`
--

LOCK TABLES `sales_orders` WRITE;
/*!40000 ALTER TABLE `sales_orders` DISABLE KEYS */;
INSERT INTO `sales_orders` VALUES (2,1,3,2,1,'SO/ICP/2025/00001','2025-09-16','PO-123-4556','2025-09-16','2025-10-10','Belagio residence lt.2 Unit OL3-28A Kawasan Mega Kuningan Barat Kav E4.3, Kuningan Timur, RT.5/RW.2, Kuningan, Kuningan Tim., Kecamatan Setiabudi, Kota Jakarta Selatan, Daerah Khusus Ibukota Jakarta 12950, Indonesia','Belagio residence lt.2 Unit OL3-28A Kawasan Mega Kuningan Barat Kav E4.3, Kuningan Timur, RT.5/RW.2, Kuningan, Kuningan Tim., Kecamatan Setiabudi, Kota Jakarta Selatan, Daerah Khusus Ibukota Jakarta 12950, Indonesia',NULL,'total',37585000.00,'amount',0.00,0.00,37585000.00,11.00,4134350.00,41719350.00,1,'ok','0015106065615000','PT. ERSINDO ADHI PERKASA','Belagio residence lt.2 Unit OL3-28A Kawasan Mega Kuningan Barat Kav E4.3, Kuningan Timur, RT.5/RW.2, Kuningan, Kuningan Tim., Kecamatan Setiabudi, Kota Jakarta Selatan, Daerah Khusus Ibukota Jakarta 12950, Indonesia','open','2025-09-16 06:54:08','2025-09-16 06:54:08');
/*!40000 ALTER TABLE `sales_orders` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-09-17 22:51:32
