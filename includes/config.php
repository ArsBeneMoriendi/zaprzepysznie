<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const APP_NAME = 'Zaprzepysznie';
const APP_SLOGAN = 'Zapisz. Przepis. Pysznie.';
const APP_BASE = '/zaprzepysznie';

const DB_HOST = 'localhost';
const DB_NAME = 'zaprzepysznie';
const DB_USER = 'root';
const DB_PASS = '';

const UPLOAD_DIR = __DIR__ . '/../uploads/';
const UPLOAD_PUBLIC_PATH = 'uploads/';
const MAX_IMAGE_SIZE = 2 * 1024 * 1024; // 2 MB

const CATEGORIES = [
    'śniadanie',
    'obiad',
    'kolacja',
    'wypieki i desery',
    'przekąski',
    'szybkie'
];

// Strony obsługiwane przez scraper
const ALLOWED_SCRAPER_DOMAINS = [
    'kwestiasmaku.com',
    'www.kwestiasmaku.com',
    'poprostupycha.com.pl',
    'www.poprostupycha.com.pl'
];
