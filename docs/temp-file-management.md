# 涓存椂鏂囦欢绠＄悊璇存槑

## 姒傝堪

`WPMCS_Temp_File_Manager` 鎻愪緵浜嗗畨鍏ㄣ€侀珮鏁堢殑涓存椂鏂囦欢绠＄悊鍔熻兘銆?

## 鍔熻兘鐗规€?

### 1. 瀹夊叏鐨勪复鏃舵枃浠跺瓨鍌?

- 鉁?鑷姩鍒涘缓淇濇姢鐩綍锛坄.htaccess` + `index.php`锛?
- 鉁?閫掑綊娓呯悊杩囨湡鏂囦欢
- 鉁?鏂囦欢鏉冮檺鎺у埗锛?40锛?
- 鉁?闃叉鐩綍閬嶅巻鏀诲嚮

### 2. 鑷姩娓呯悊

- 鉁?瀹氭椂浠诲姟鑷姩娓呯悊锛堟瘡鏃ワ級
- 鉁?鍙厤缃繃鏈熸椂闂达紙榛樿 24 灏忔椂锛?
- 鉁?閫掑綊鍒犻櫎瀛愮洰褰?

### 3. 缁熻鐩戞帶

- 鉁?鏂囦欢鏁伴噺缁熻
- 鉁?鎬诲ぇ灏忕粺璁?
- 鉁?杩囨湡鏂囦欢鏁伴噺
- 鉁?鏈€鏂?鏈€鏃ф枃浠舵椂闂?

## 浣跨敤鏂规硶

### 鍒涘缓涓存椂鏂囦欢

```php
// 鍒涘缓涓存椂鏂囨湰鏂囦欢
$filepath = WPMCS_Temp_File_Manager::create_temp_file(
	'This is temporary content',
	'upload-',        // 鏂囦欢鍚嶅墠缂€
	'txt'            // 鏂囦欢鎵╁睍鍚?
);

// 鍒涘缓涓存椂浜岃繘鍒舵枃浠?
$image_data = file_get_contents( 'image.jpg' );
$temp_image = WPMCS_Temp_File_Manager::create_temp_file(
	$image_data,
	'image-',
	'jpg'
);
```

### 鍒涘缓涓存椂鐩綍

```php
// 鍒涘缓涓存椂鐩綍
$temp_dir = WPMCS_Temp_File_Manager::create_temp_dir( 'batch-upload-' );

// 鍦ㄧ洰褰曚腑鍒涘缓鏂囦欢
file_put_contents( $temp_dir . '/file1.txt', 'content' );
file_put_contents( $temp_dir . '/file2.txt', 'content' );

// 娓呯悊鏃惰嚜鍔ㄩ€掑綊鍒犻櫎
```

### 鎵嬪姩娓呯悊涓存椂鏂囦欢

```php
// 娓呯悊瓒呰繃 24 灏忔椂鐨勬枃浠讹紙榛樿锛?
$count = WPMCS_Temp_File_Manager::cleanup_temp_files();
echo "Cleaned $count files";

// 娓呯悊瓒呰繃 1 灏忔椂鐨勬枃浠?
$count = WPMCS_Temp_File_Manager::cleanup_temp_files( 3600 );
echo "Cleaned $count files";

// 寮哄埗娓呯悊鎵€鏈変复鏃舵枃浠?
$count = WPMCS_Temp_File_Manager::clear_all_temp_files();
echo "Cleared all $count files";
```

### 鑾峰彇缁熻淇℃伅

```php
$stats = WPMCS_Temp_File_Manager::get_stats();

echo "涓存椂鐩綍: " . ( $stats['temp_dir_exists'] ? '瀛樺湪' : '涓嶅瓨鍦? ) . "\n";
echo "鏂囦欢鏁伴噺: " . $stats['total_files'] . "\n";
echo "鎬诲ぇ灏? " . WPMCS_Temp_File_Manager::format_size( $stats['total_size'] ) . "\n";
echo "杩囨湡鏂囦欢: " . $stats['expired_files'] . "\n";

if ( $stats['oldest_file'] > 0 ) {
	echo "鏈€鏃ф枃浠? " . date( 'Y-m-d H:i:s', $stats['oldest_file'] ) . "\n";
}
if ( $stats['newest_file'] > 0 ) {
	echo "鏈€鏂版枃浠? " . date( 'Y-m-d H:i:s', $stats['newest_file'] ) . "\n";
}
```

## 瀹夊叏鐗规€?

### 1. 鐩綍淇濇姢

涓存椂鐩綍鑷姩鍒涘缓淇濇姢鏂囦欢锛?

**`.htaccess`**
```apache
Deny from all
```

**`index.php`**
```php
<?php
// Silence is golden.
```

杩欓槻姝簡锛?
- 鉂?鐩存帴璁块棶涓存椂鏂囦欢
- 鉂?鐩綍鍒楄〃鏆撮湶
- 鉂?鏈巿鏉冭闂?

### 2. 鏂囦欢鏉冮檺

- 鍒涘缓鐨勬枃浠舵潈闄愶細`640` (鎵€鏈夎€呰鍐欙紝缁勫彧璇?
- 鐩綍鏉冮檺锛歚755` (WordPress 榛樿)

### 3. 鍞竴鏂囦欢鍚?

浣跨敤 `wp_generate_password()` 鐢熸垚闅忔満鏂囦欢鍚嶏細
```
wpmcs-aB3dEf5gH7jK9.txt
```

### 4. 璺緞楠岃瘉

娓呯悊鍓嶉獙璇佺洰褰曞悕锛岄槻姝㈣鍒狅細
```php
$dir_name = basename( $temp_dir );
if ( 'wpmcs-temp' === $dir_name ) {
    // 瀹夊叏娓呯悊
}
```

## 瀹氭椂娓呯悊

鎻掍欢浼氳嚜鍔ㄦ敞鍐屽畾鏃朵换鍔★細

```php
// 姣忔棩娓呯悊杩囨湡涓存椂鏂囦欢
wp_schedule_event( time(), 'daily', 'wpmcs_cleanup_temp_files' );
```

鎵ц鏃朵細娓呯悊锛?
- 鉁?瓒呰繃 24 灏忔椂鐨勬枃浠?
- 鉁?绌哄瓙鐩綍
- 鉁?閫掑綊瀛愮洰褰曚腑鐨勬枃浠?

## 鐩綍缁撴瀯

```
wp-content/uploads/
鈹斺攢鈹€ wpmcs-temp/
    鈹溾攢鈹€ .htaccess           # 璁块棶淇濇姢
    鈹溾攢鈹€ index.php          # 闃叉鐩綍鍒楄〃
    鈹溾攢鈹€ wpmcs-aB3dEf5.txt   # 涓存椂鏂囦欢
    鈹溾攢鈹€ wpmcs-xY9zW2.jpg
    鈹斺攢鈹€ batch-upload-/
        鈹溾攢鈹€ .htaccess       # 瀛愮洰褰曚繚鎶?
        鈹溾攢鈹€ file1.txt
        鈹斺攢鈹€ file2.txt
```

## 鍗歌浇娓呯悊

鍗歌浇鎻掍欢鏃朵細锛?
1. 鉁?閫掑綊鍒犻櫎 `wpmcs-temp` 鐩綍
2. 鉁?鍒犻櫎鎵€鏈夋枃浠跺拰瀛愮洰褰?
3. 鉁?璁板綍鍒犻櫎鐨勬枃浠舵暟閲?

## 浣跨敤鍦烘櫙

### 鍦烘櫙 1锛氫笂浼犲墠涓存椂瀛樺偍

```php
// 鐢ㄦ埛涓婁紶鏂囦欢鍒颁复鏃剁洰褰?
$temp_file = WPMCS_Temp_File_Manager::create_temp_file(
	$file_content,
	'upload-',
	'tmp'
);

// 澶勭悊鏂囦欢
$processed = process_file( $temp_file );

// 涓婁紶鍒颁簯瀛樺偍鍚庯紝涓存椂鏂囦欢浼氳嚜鍔ㄦ竻鐞?
upload_to_cloud( $processed );
```

### 鍦烘櫙 2锛氭壒閲忔搷浣滅紦瀛?

```php
// 鍒涘缓涓存椂鐩綍瀛樺偍鎵归噺涓婁紶鐨勬枃浠?
$temp_dir = WPMCS_Temp_File_Manager::create_temp_dir( 'batch-' );

// 淇濆瓨鏂囦欢鍒颁复鏃剁洰褰?
foreach ( $files as $file ) {
	file_put_contents( $temp_dir . '/' . $file['name'], $file['content'] );
}

// 鎵归噺涓婁紶
batch_upload( $temp_dir );

// 瀹氭椂浠诲姟浼氳嚜鍔ㄦ竻鐞?
```

### 鍦烘櫙 3锛氭棩蹇楄浆鍌?

```php
// 灏嗗ぇ鏃ュ織杞偍鍒颁复鏃舵枃浠?
$temp_log = WPMCS_Temp_File_Manager::create_temp_file(
	$log_content,
	'log-',
	'log'
);

// 鍙戦€佹棩蹇楀埌杩滅▼鏈嶅姟
send_log_to_remote( $temp_log );

// 鑷姩娓呯悊
```

## 鎬ц兘浼樺寲

### 1. 閫掑綊鎵弿浼樺寲

- 浣跨敤 `scandir()` 鑰岄潪 `glob()`
- 璺宠繃鐗规畩鐩綍锛坄.` 鍜?`..`锛?
- 璺宠繃淇濇姢鏂囦欢锛坄.htaccess`, `index.php`锛?

### 2. 鏂囦欢鏃堕棿妫€鏌?

- 浣跨敤 `filemtime()` 妫€鏌ヤ慨鏀规椂闂?
- 鍙鐞嗚繃鏈熸枃浠?
- 鍑忓皯涓嶅繀瑕佺殑鎿嶄綔

### 3. 鎵归噺鍒犻櫎

- 鍒犻櫎鍓嶆敹闆嗘墍鏈夎矾寰?
- 閬垮厤閲嶅鎵弿
- 鍑忓皯绯荤粺璋冪敤

## 鏁呴殰鎺掗櫎

### 闂 1锛氫复鏃舵枃浠舵湭娓呯悊

**鍘熷洜**锛氬畾鏃朵换鍔℃湭杩愯

**瑙ｅ喅**锛?
```php
// 妫€鏌ュ畾鏃朵换鍔?
$next = wp_next_scheduled( 'wpmcs_cleanup_temp_files' );
if ( $next ) {
	echo "Next cleanup: " . date( 'Y-m-d H:i:s', $next );
} else {
	echo "Cron not scheduled";
}

// 鎵嬪姩瑙﹀彂娓呯悊
$count = WPMCS_Temp_File_Manager::cleanup_temp_files();
```

### 闂 2锛氭棤娉曞垱寤轰复鏃舵枃浠?

**鍘熷洜**锛氭潈闄愪笉瓒?

**瑙ｅ喅**锛?
```bash
# 妫€鏌ヤ笂浼犵洰褰曟潈闄?
ls -la wp-content/uploads/

# 纭繚鍙啓
chmod 755 wp-content/uploads/
```

### 闂 3锛氫复鏃舵枃浠剁洿鎺ュ彲璁块棶

**鍘熷洜**锛歚.htaccess` 鏈敓鏁堬紙Nginx锛?

**瑙ｅ喅**锛?
鍦?Nginx 閰嶇疆涓坊鍔狅細
```nginx
location ~* /wp-content/uploads/wpmcs-temp/ {
    deny all;
    return 404;
}
```

## 鏈€浣冲疄璺?

### 1. 鍙婃椂娓呯悊

- 浣跨敤瀹屼复鏃舵枃浠跺悗灏藉揩鍒犻櫎
- 涓嶈渚濊禆鑷姩娓呯悊澶勭悊鏁忔劅鏁版嵁
- 鏁忔劅鏁版嵁搴斿湪澶勭悊鍚庣珛鍗冲垹闄?

### 2. 浣跨敤鏈夋剰涔夌殑鏂囦欢鍚嶅墠缂€

```php
// 濂?
$temp_file = WPMCS_Temp_File_Manager::create_temp_file(
	$data,
	'upload-',  // 鏄庣‘鏍囪瘑鐢ㄩ€?
	'tmp'
);

// 宸?
$temp_file = WPMCS_Temp_File_Manager::create_temp_file(
	$data,
	'',        // 涓嶆竻妤氱敤閫?
	'tmp'
);
```

### 3. 鐩戞帶涓存椂鏂囦欢

瀹氭湡妫€鏌ョ粺璁′俊鎭細
```php
$stats = WPMCS_Temp_File_Manager::get_stats();
if ( $stats['total_size'] > 100 * 1024 * 1024 ) { // 100MB
	// 璁板綍璀﹀憡
	error_log( 'Temp files size exceeds 100MB' );
}
```

### 4. 浣跨敤鍚堥€傜殑杩囨湡鏃堕棿

```php
// 鐭湡缂撳瓨锛?灏忔椂锛?
WPMCS_Temp_File_Manager::cleanup_temp_files( 3600 );

// 涓湡缂撳瓨锛?澶╋紝榛樿锛?
WPMCS_Temp_File_Manager::cleanup_temp_files();

// 闀挎湡缂撳瓨锛?鍛級
WPMCS_Temp_File_Manager::cleanup_temp_files( 604800 );
```

## 鐩稿叧鏂囦欢

- `includes/class-wpmcs-temp-file-manager.php` - 涓存椂鏂囦欢绠＄悊鍣ㄧ被
- `uninstall.php` - 鍗歌浇娓呯悊閫昏緫
- `wp-multi-cloud-storage.php` - 瀹氭椂浠诲姟娉ㄥ唽

## 鏇存柊鏃ュ織

- **v0.2.1** - 保持临时文件管理能力，并同步日志与统计修复
  - 自动创建保护目录
  - 瀹氭椂娓呯悊杩囨湡鏂囦欢
  - 缁熻鐩戞帶鍔熻兘
  - 瀹夊叏鏂囦欢鏉冮檺
