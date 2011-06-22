-- phpMyAdmin SQL Dump
-- version 3.3.2deb1
-- http://www.phpmyadmin.net
--
-- Хост: localhost
-- Время создания: Июн 22 2011 г., 12:10
-- Версия сервера: 5.1.41
-- Версия PHP: 5.3.2-1ubuntu4.9

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- База данных: `dev.sandbox`
--

-- --------------------------------------------------------

--
-- Структура таблицы `objects`
--

DROP TABLE IF EXISTS `objects`;
CREATE TABLE IF NOT EXISTS `objects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `class_id` char(32) CHARACTER SET ascii NOT NULL,
  `name` char(16) NOT NULL,
  `parameter1` int(11) NOT NULL,
  `parameter2` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `parameter1` (`parameter1`),
  KEY `parameter2` (`parameter2`),
  KEY `class_id` (`class_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=5 ;

--
-- Дамп данных таблицы `objects`
--

INSERT INTO `objects` (`id`, `class_id`, `name`, `parameter1`, `parameter2`) VALUES
(1, 'user', 'foxel', 0, 0),
(2, 'user', '', 0, 0),
(4, 'newsitem', '', 0, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `object_class_fields`
--

DROP TABLE IF EXISTS `object_class_fields`;
CREATE TABLE IF NOT EXISTS `object_class_fields` (
  `class_id` char(32) CHARACTER SET ascii NOT NULL,
  `key` char(16) CHARACTER SET armscii8 NOT NULL,
  `type` enum('int','float','str','text') CHARACTER SET ascii NOT NULL,
  PRIMARY KEY (`class_id`,`key`),
  KEY `type` (`type`),
  KEY `class_id` (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `object_class_fields`
--

INSERT INTO `object_class_fields` (`class_id`, `key`, `type`) VALUES
('user', 'birthday', 'int'),
('user', 'sex', 'int'),
('user', 'visits', 'int'),
('user', 'city', 'str'),
('user', 'url', 'str'),
('user', 'about', 'text');

-- --------------------------------------------------------

--
-- Структура таблицы `object_texts`
--

DROP TABLE IF EXISTS `object_texts`;
CREATE TABLE IF NOT EXISTS `object_texts` (
  `obj_id` int(10) unsigned NOT NULL,
  `key` char(16) CHARACTER SET ascii NOT NULL,
  `text` text NOT NULL,
  `last_edited` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`obj_id`,`key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `object_texts`
--

INSERT INTO `object_texts` (`obj_id`, `key`, `text`, `last_edited`) VALUES
(1, 'about', 'My name is Foxel ^)', '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Структура таблицы `object_values`
--

DROP TABLE IF EXISTS `object_values`;
CREATE TABLE IF NOT EXISTS `object_values` (
  `obj_id` int(10) unsigned NOT NULL,
  `key` char(16) CHARACTER SET ascii NOT NULL,
  `int` int(11) DEFAULT NULL,
  `str` char(255) DEFAULT NULL,
  `float` decimal(10,0) DEFAULT NULL,
  `last_edited` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`obj_id`,`key`),
  KEY `int` (`int`),
  KEY `str` (`str`),
  KEY `float` (`float`),
  KEY `key` (`key`),
  KEY `last_edited` (`last_edited`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Дамп данных таблицы `object_values`
--

INSERT INTO `object_values` (`obj_id`, `key`, `int`, `str`, `float`, `last_edited`) VALUES
(1, 'city', NULL, 'Tomsk', NULL, '2011-06-22 11:15:47'),
(1, 'url', NULL, 'http://foxel.quickfox.ru', NULL, '2011-06-22 11:15:47');

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `object_values`
--
ALTER TABLE `object_values`
  ADD CONSTRAINT `object_values_ibfk_1` FOREIGN KEY (`obj_id`) REFERENCES `objects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
