<?php 
/**
 * CuymangaAPI - PHP Version
 *
 * Version: 1.1.0
 * Author: whyudacok
 * Generated with AI assistance
 * License: MIT
 *
 * API scraper manga untuk Komikindo (PHP Version).
 *
 * Dependencies:
 * - Simple HTML DOM: https://simplehtmldom.sourceforge.io/
 */

// Konfigurasi
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('X-Content-Type-Options: nosniff');
define('BASE_URL', 'https://komikindo2.com'); // pantau terus bang 
define('USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:118.0) Gecko/20100101 Firefox/118.0');
define('RATE_LIMIT', 60); // Batas request per menit
define('RATE_LIMIT_WINDOW', 60); // Jendela waktu dalam detik

// library yang diperlukan
require_once 'simple_html_dom.php';

/**
* Fungsi untuk memeriksa rate limit
*
* @return bool True jika masih dalam batas, False jika melebihi batas
*/
function checkRateLimit() {
  $clientIP = $_SERVER['REMOTE_ADDR'];
  $cacheFile = sys_get_temp_dir() . '/rate_limit_' . md5($clientIP) . '.txt';

  if (file_exists($cacheFile)) {
    $data = unserialize(file_get_contents($cacheFile));
    if (time() - $data['time'] < RATE_LIMIT_WINDOW) {
      if ($data['count'] >= RATE_LIMIT) {
        return false;
      }
      $data['count']++;
    } else {
      $data = ['count' => 1,
        'time' => time()];
    }
  } else {
    $data = ['count' => 1,
      'time' => time()];
  }

  file_put_contents($cacheFile, serialize($data));
  return true;
}

/**
*
* @param array $data Data yang akan ditampilkan
* @param int $statusCode HTTP status code
* @return void
*/
function displayPrettyJson($data, $statusCode = 200) {
  // Bersihkan output buffer
  if (ob_get_length()) ob_clean();

  // Set header untuk JSON dan mencegah caching
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Cache-Control: post-check=0, pre-check=0', false);
  header('Pragma: no-cache');

  // Set HTTP status code
  http_response_code($statusCode);

  // Encode JSON dengan opsi yang memastikan format yang baik
  $jsonOutput = json_encode($data,
    JSON_PRETTY_PRINT |
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES |
    JSON_PRESERVE_ZERO_FRACTION
  );

  // Jika encoding gagal, berikan pesan error
  if ($jsonOutput === false) {
    $jsonOutput = json_encode([
      'status' => false,
      'data' => null,
      'message' => 'JSON encoding error: ' . json_last_error_msg()
    ]);
  }

  // Output JSON
  echo $jsonOutput;
  exit;
}

/**
* Fungsi untuk mengambil HTML menggunakan cURL
*
* @param string $url URL yang akan diambil
* @return string HTML content
* @throws Exception jika terjadi kesalahan
*/
function fetchHTML($url) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 30);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: ' . USER_AGENT
  ]);
  $response = curl_exec($ch);
  $error = curl_error($ch);
  curl_close($ch);

  if ($response === false) {
    throw new Exception('Error cURL: ' . $error);
  }

  return $response;
}

/**
* Fungsi untuk mengubah URL menjadi path
*
* @param string $url URL lengkap
* @return string Path dari URL
*/
function getPathFromUrl($url) {
  if (strpos($url, BASE_URL) === 0) {
    return parse_url($url, PHP_URL_PATH);
  }
  return $url;
}

/**
* Fungsi untuk membersihkan teks
*
* @param string $text Teks yang akan dibersihkan
* @return string Teks yang sudah dibersihkan
*/
function cleanText($text) {
  return trim(preg_replace('/\s+/', ' ', $text));
}

/**
* Mengambil data komik terbaru
*
* @param int $page Nomor halaman
* @return array Data komik terbaru
*/
function getLatestKomik($page = 1) {
  $url = BASE_URL . "/komik-terbaru/page/$page";

  try {
    $htmlContent = fetchHTML($url);
    $html = str_get_html($htmlContent);

    if (!$html) {
      throw new Exception('Gagal mengambil HTML dari URL: ' . $url);
    }

    $results = [];
    $komikPopuler = [];

    // Mengambil data animepost
    foreach ($html->find('.animepost') as $el) {
      $title = trim($el->find('.tt h4', 0)->plaintext ?? 'Tidak ada judul');
      $link = getPathFromUrl($el->find('a[rel="bookmark"]', 0)->href ?? '');
      $image = $el->find('img[itemprop="image"]', 0)->src ?? '';
      $type = end(explode(' ', $el->find('.typeflag', 0)->class ?? 'Tidak ada tipe'));
      $color = trim($el->find('.warnalabel', 0)->plaintext ?? 'Hitam');

      $chapter = [];
      foreach ($el->find('.lsch') as $chapterEl) {
        $chTitle = trim(str_replace('Ch.', 'Chapter', $chapterEl->find('a', 0)->plaintext ?? 'Chapter tanpa judul'));
        $chLink = getPathFromUrl($chapterEl->find('a', 0)->href ?? '');
        $chDate = trim($chapterEl->find('.datech', 0)->plaintext ?? 'Tidak ada tanggal');
        $chapter[] = [
          'judul' => $chTitle,
          'link' => $chLink,
          'tanggal_rilis' => $chDate,
        ];
      }

      $results[] = [
        'judul' => $title,
        'link' => $link,
        'gambar' => $image,
        'tipe' => $type,
        'warna' => $color,
        'chapter' => $chapter,
      ];
    }

    // Mengambil data populer
    foreach ($html->find('.serieslist.pop li') as $el) {
      $rank = trim($el->find('.ctr', 0)->plaintext ?? 'Tidak ada peringkat');
      $title = trim($el->find('h4 a', 0)->plaintext ?? 'Tidak ada judul');
      $link = getPathFromUrl($el->find('h4 a', 0)->href ?? '');
      $image = $el->find('.imgseries img', 0)->src ?? '';
      $author = trim($el->find('.author', 0)->plaintext ?? 'Penulis tidak diketahui');
      $rating = trim(end(explode(' ', $el->find('.loveviews', 0)->plaintext ?? 'Tidak ada rating')));

      $komikPopuler[] = [
        'peringkat' => $rank,
        'judul' => $title,
        'link' => $link,
        'penulis' => $author,
        'rating' => $rating,
        'gambar' => $image,
      ];
    }

    // Mengambil total halaman dari pagination
    $pagination = $html->find('.pagination a.page-numbers');
    $totalPages = (int) trim($pagination[count($pagination) - 2]->plaintext ?? 1);

    return [
      'total_halaman' => $totalPages,
      'komik' => $results,
      'komik_populer' => $komikPopuler,
    ];
  } catch (Exception $e) {
    throw $e;
  }
}

/**
* Mengambil detail komik
*
* @param string $komikId ID komik
* @return array Detail komik
*/
function getKomikDetail($komikId) {
  $url = BASE_URL . "/komik/$komikId";

  try {
    $htmlContent = fetchHTML($url);
    $html = str_get_html($htmlContent);

    if (!$html) {
      throw new Exception('Gagal mengambil HTML dari URL: ' . $url);
    }

    $title = trim($html->find('h1.entry-title', 0)->plaintext ?? 'Tidak ada judul');
    $description = cleanText($html->find('.entry-content.entry-content-single[itemprop="description"] p', 0)->plaintext ?? 'Tidak ada desk');

    $detail = [
      'judul_alternatif' => null,
      'status' => null,
      'pengarang' => null,
      'ilustrator' => null,
      'jenis_komik' => null,
      'tema' => null
    ];

    foreach ($html->find('.spe span') as $el) {
      $key = trim($el->find('b', 0)->plaintext);
      $value = cleanText(str_replace("$key:", '', $el->plaintext));
      $key = trim(str_replace(":", "", $key));
      $value = trim(str_replace("$key:", "", $value));
      switch (strtolower($key)) {
        case 'judul alternatif':
          $detail['judul_alternatif'] = $value;
          break;
        case 'status':
          $detail['status'] = $value;
          break;
        case 'pengarang':
          $detail['pengarang'] = $value;
          break;
        case 'ilustrator':
          $detail['ilustrator'] = $value;
          break;
        case 'tema':
          $detail['tema'] = $value;
          break;
        case 'jenis komik':
          $detail['jenis_komik'] = $value;
          break;
      }
    }
    $image = $html->find('.thumb img', 0)->src ?? '';
    $rating = trim($html->find('.rtg i[itemprop="ratingValue"]', 0)->plaintext ?? 'Tidak ada rating');
    $votes = trim($html->find('.votescount', 0)->plaintext ?? 'Tidak ada votes');

    $chapters = [];
    foreach ($html->find('.listeps ul li') as $el) {
      $chapterTitle = trim($el->find('.lchx a', 0)->plaintext ?? 'Tidak ada judul');
      $chapterLink = getPathFromUrl($el->find('.lchx a', 0)->href ?? '');
      $releaseTime = trim($el->find('.dt a', 0)->plaintext ?? 'Tidak ada waktu rilis');
      $chapters[] = [
        'judul_chapter' => $chapterTitle,
        'link_chapter' => $chapterLink,
        'waktu_rilis' => $releaseTime
      ];
    }

    $chapterAwal = null;
    $chapterTerbaru = null;
    $epsbrDivs = $html->find('.epsbr');
    if (count($epsbrDivs) >= 2) {
      $chapterAwal = [
        'judul_chapter' => trim($epsbrDivs[0]->find('a', 0)->plaintext ?? 'Tidak ada judul'),
        'link_chapter' => getPathFromUrl($epsbrDivs[0]->find('a', 0)->href ?? '')
      ];
      $chapterTerbaru = [
        'judul_chapter' => trim($epsbrDivs[1]->find('a', 0)->plaintext ?? 'Tidak ada judul'),
        'link_chapter' => getPathFromUrl($epsbrDivs[1]->find('a', 0)->href ?? '')
      ];
    }

    $similarManga = [];
    foreach ($html->find('.serieslist ul li') as $el) {
      $similarManga[] = [
        'judul' => trim($el->find('.leftseries h4 a', 0)->plaintext ?? 'Tidak ada judul'),
        'link' => getPathFromUrl($el->find('.leftseries h4 a', 0)->href ?? ''),
        'gambar' => $el->find('.imgseries a img', 0)->src ?? '',
        'desk' => trim($el->find('.excerptmirip', 0)->plaintext ?? 'Tidak ada desk')
      ];
    }

    $spoilerImage = [];
    foreach ($html->find('#spoiler .spoiler-img img') as $el) {
      $spoilerImage[] = $el->src ?? '';
    }

    $id = str_replace('post-', '', $html->find('article', 0)->id ?? 'Tidak ada ID');
    $genre = [];
    foreach ($html->find('.genre-info a') as $el) {
      $genre[] = [
        'nama' => trim($el->plaintext ?? 'Tidak ada genre'),
        'link' => str_replace('/genres/', '', getPathFromUrl($el->href ?? ''))
      ];
    }

    return [
      'id' => $id,
      'judul' => $title,
      'gambar' => $image,
      'rating' => $rating,
      'votes' => $votes,
      'detail' => $detail,
      'genre' => $genre,
      'desk' => $description,
      'chapter_awal' => $chapterAwal,
      'chapter_terbaru' => $chapterTerbaru,
      'daftar_chapter' => $chapters,
      'chapter_spoiler' => $spoilerImage,
      'komik_serupa' => $similarManga
    ];
  } catch (Exception $e) {
    throw $e;
  }
}

/**
* Mengambil data chapter komik
*
* @param string $chapterId ID chapter
* @return array Data chapter
*/
function getKomikChapter($chapterId) {
  $url = BASE_URL . "/$chapterId";

  try {
    $htmlContent = fetchHTML($url);
    $html = str_get_html($htmlContent);

    if (!$html) {
      throw new Exception('Gagal mengambil HTML dari URL: ' . $url);
    }

    $results = [];
    $results['id'] = str_replace('post-', '', $html->find('article', 0)->id ?? 'Tidak ada ID');
    $results['judul'] = trim($html->find('.entry-title', 0)->plaintext ?? 'Tidak ada judul');
    $results['navigasi'] = [
      'sebelumnya' => getPathFromUrl($html->find('a[rel="prev"]', 0)->href ?? ''),
      'selanjutnya' => getPathFromUrl($html->find('a[rel="next"]', 0)->href ?? '')
    ];

    $allchElement = $html->find('a div.icol.daftarch', 0);
    $results['semua_chapter'] = $allchElement ? getPathFromUrl($allchElement->parent()->href) : null;

    $results['gambar'] = [];
    foreach ($html->find('.chapter-image img') as $index => $el) {
      $imgSrc = $el->src;
      if ($imgSrc) {
        $results['gambar'][] = [
          'id' => $index + 1,
          'url' => $imgSrc
        ];
      }
    }

    $thumbnail = $html->find('div.thumb img', 0);
    $results['thumbnail'] = $thumbnail ? [
      'url' => $thumbnail->src,
      'judul' => $thumbnail->title ?? 'Tidak ada judul'
    ] : null;

    $results['info_komik'] = [
      'judul' => trim($html->find('.infox h2', 0)->plaintext ?? 'Tidak ada judul'),
      'desk' => trim($html->find('.shortcsc', 0)->plaintext ?? 'Tidak ada desk'),
      'chapter' => []
    ];

    foreach ($html->find('#chapter_list .lchx a') as $el) {
      $results['info_komik']['chapter'][] = [
        'judul_chapter' => trim($el->plaintext),
        'link_chapter' => getPathFromUrl($el->href)
      ];
    }

    return $results;
  } catch (Exception $e) {
    throw $e;
  }
}

/**
* Mengambil daftar komik (library)
*
* @param array $params Parameter pencarian
* @return array Daftar komik
*/
function getKomikLibrary($params) {
  $url = '';

  if (isset($params['genre'])) {
    $genre = $params['genre'];
    $page = $params['page'] ?? 1;
    $url = BASE_URL . "/daftar-manga/page/$page/?genre%5B0%5D=" . urlencode($genre) . "&status&type&format&order&title";
  } elseif (isset($params['page']) && isset($params['s'])) {
    $page = $params['page'];
    $search = $params['s'];
    $url = BASE_URL . "/page/$page/?s=" . urlencode($search);
  } elseif (isset($params['page']) && isset($params['type'])) {
    $page = $params['page'];
    $type = $params['type'];
    $url = BASE_URL . "/daftar-manga/page/$page/?status&type=$type&format&order&title";
  } elseif (isset($params['daftar'])) {
    $daftar = $params['daftar'];
    $url = $daftar == 1 ? BASE_URL . "/daftar-manga/" : BASE_URL . "/daftar-manga/page/$daftar/";
  } else {
    $url = BASE_URL . "/daftar-manga";
  }

  try {
    $htmlContent = fetchHTML($url);
    $html = str_get_html($htmlContent);

    if (!$html) {
      throw new Exception("Gagal mengambil atau mengurai URL: $url");
    }

    $results = [];
    $komikPopuler = [];

    // Mengambil komik berdasarkan genre atau kategori
    foreach ($html->find('.animepost') as $el) {
      $title = trim($el->find('.tt h4', 0)->plaintext ?? 'Tidak ada judul');
      $rating = trim($el->find('.rating i', 0)->plaintext ?? '0');
      $link = $el->find('a[rel="bookmark"]', 0)->href ?? '';
      $image = $el->find('img[itemprop="image"]', 0)->src ?? '';
      $type = $el->find('.typeflag', 0) ? end(explode(' ', $el->find('.typeflag', 0)->class)) : 'Tidak ada tipe';
      $color = trim($el->find('.warnalabel', 0)->plaintext ?? 'Hitam');
      $link = getPathFromUrl($link);

      $results[] = [
        'judul' => $title,
        'rating' => $rating,
        'link' => $link,
        'gambar' => $image,
        'tipe' => $type,
        'warna' => $color,
      ];
    }

    // Mengambil komik populer
    foreach ($html->find('.serieslist.pop li') as $el) {
      $rank = trim($el->find('.ctr', 0)->plaintext ?? 'Tidak ada peringkat');
      $title = trim($el->find('h4 a', 0)->plaintext ?? 'Tidak ada judul');
      $link = $el->find('h4 a', 0)->href ?? '';
      $image = $el->find('.imgseries img', 0)->src ?? '';
      $author = trim($el->find('.author', 0)->plaintext ?? 'Penulis tidak diketahui');
      $rating = $el->find('.loveviews', 0) ? trim(end(explode(' ', $el->find('.loveviews', 0)->plaintext))) : 'Tidak ada rating';
      $link = getPathFromUrl($link);

      $komikPopuler[] = [
        'judul' => $title,
        'link' => $link,
        'peringkat' => $rank,
        'penulis' => $author,
        'rating' => $rating,
        'gambar' => $image,
      ];
    }

    // Menentukan total halaman untuk pagination
    $pagination = $html->find('.pagination a.page-numbers', -2);
    $totalPages = $pagination ? (int) trim($pagination->plaintext) : 1;

    return [
      'total_halaman' => $totalPages,
      'komik' => $results,
      'komik_populer' => $komikPopuler,
    ];
  } catch (Exception $e) {
    throw $e;
  }
}

try {
  // Periksa rate limit
  if (!checkRateLimit()) {
    displayPrettyJson([
      'status' => false,
      'message' => 'Rate limit exceeded. Please try again later.',
      'data' => null
    ], 429); // 429 Too Many Requests
  }

  // Validasi input
  $allowedParams = ['latest',
    'komik',
    'chapter',
    'library',
    'genre',
    's',
    'daftar',
    'type',
    'page'];
  foreach ($_GET as $key => $value) {
    if (!in_array($key, $allowedParams)) {
      displayPrettyJson([
        'status' => false,
        'message' => 'Invalid parameter: ' . $key,
        'data' => null
      ], 400); // 400 Bad Request
    }
  }

  // Menentukan jenis permintaan berdasarkan parameter
  if (isset($_GET['latest'])) {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $data = getLatestKomik($page);
    displayPrettyJson([
      'status' => true,
      'message' => 'OK',
      'data' => $data

    ]);
  } elseif (isset($_GET['komik'])) {
    $komikId = $_GET['komik'];
    $data = getKomikDetail($komikId);
    displayPrettyJson([
      'status' => true,
      'message' => 'OK',
      'data' => $data
    ]);
  } elseif (isset($_GET['chapter'])) {
    $chapterId = $_GET['chapter'];
    $data = getKomikChapter($chapterId);
    displayPrettyJson([
      'status' => true,
      'message' => 'OK',
      'data' => $data
    ]);
  } elseif (isset($_GET['library']) || isset($_GET['genre']) || isset($_GET['s']) || isset($_GET['daftar']) || isset($_GET['type'])) {
    $data = getKomikLibrary($_GET);
    displayPrettyJson([
      'status' => true,
      'message' => 'OK',
      'data' => $data
    ]);
  } else {
    // Jika tidak ada parameter yang cocok, tampilkan dokumentasi API
    $documentation = [
      'name' => 'CuymangaAPI',
      'version' => '1.1.0',
      'description' => 'CuymangaAPI adalah REST API untuk web scraping yang mengambil data komik dari Komikindo2.com menggunakan Simple HTML DOM PHP.',
      'author' => 'whyudacok',
      'website' => 'https://whyuck.my.id',
      'rate_limit' => RATE_LIMIT . ' request per ' . RATE_LIMIT_WINDOW . ' detik',
      'endpoint' => [
        [
          'path' => '?latest=1&page=1',
          'desk' => 'Mendapatkan daftar komik terbaru',
          'parameter' => [
            'page' => 'Nomor halaman (opsional, default: 1)'
          ]
        ],
        [
          'path' => '?komik=slug-komik',
          'desk' => 'Mendapatkan detail komik',
          'parameter' => [
            'komik' => 'ID atau slug komik'
          ]
        ],
        [
          'path' => '?chapter=slug-komik-chapter-1',
          'desk' => 'Mendapatkan data chapter komik',
          'parameter' => [
            'chapter' => 'ID atau slug chapter'
          ]
        ],
        [
          'path' => '?genre=action&page=1',
          'desk' => 'Mendapatkan daftar komik berdasarkan genre',
          'parameter' => [
            'genre' => 'Nama genre',
            'page' => 'Nomor halaman (Harus, default: 1)'
          ]
        ],
        [
          'path' => '?s=naruto&page=1',
          'desk' => 'Mencari komik',
          'parameter' => [
            's' => 'Kata kunci pencarian',
            'page' => 'Nomor halaman (Harus, default: 1)'
          ]
        ],
        [
          'path' => '?daftar=1',
          'desk' => 'Mendapatkan daftar semua komik',
          'parameter' => [
            'daftar' => 'Nomor halaman (Harus, default: 1)'
          ]
        ],
        [
          'path' => '?type=manga&page=1',
          'desk' => 'Mendapatkan daftar komik berdasarkan tipe',
          'parameter' => [
            'type' => 'Tipe komik (manga, manhwa, manhua)',
            'page' => 'Nomor halaman (Harus, default: 1)'
          ]
        ]
      ]
    ];
    displayPrettyJson([
      'status' => true,
      'message' => 'Docs CuymangaAPI',
      'data' => $documentation
    ]);
  }
} catch (Exception $e) {
  displayPrettyJson([
    'status' => false,
    'data' => null,
    'message' => 'Terjadi kesalahan: ' . $e->getMessage()
  ], 500); // 500 Internal Server Error
}
?>
