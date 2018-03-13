defined('IN_MANAGER_MODE') or die();
$error_page = isset($error_page) ? $error_page : 'exit.php'; 
$min = isset($min) ? $min : 4; 
$limit = isset($limit) ? $limit : 20; 
$tags = isset($tags) ? $tags : 'h1,h2,h3,h4,h5,h6,b,i,strong'; 
$link_title = isset($link_title) ? $link_title : true; 
$circle_link = isset($circle_link) ? $circle_link : true; 
$external_link = isset($external_link) ? $external_link : true; 
$img_alt = isset($img_alt) ? $img_alt : true; 
$generate_tag = isset($generate_tag) ? $generate_tag : true; 
$one_line = isset($one_line) ? $one_line : true; 

$favicon_generate = isset($favicon_generate) ? $favicon_generate : false;
$bg = isset($bg) ? $bg : "FFFFFF";
$img = isset($img) ? $img : "";



$e =&$modx->Event;
if (!function_exists('compress_html')) { 
	function compress_html($compress)
	{			
		$compress = str_replace("\n", '', $compress);
		$compress = str_replace("\s", '', $compress);
		$compress = str_replace("\r", '', $compress);
		$compress = str_replace("\t", '', $compress);
		$compress = preg_replace('/(?:(?<=\>)|(?<=\/\>))\s+(?=\<\/?)/', '', $compress);
		$compress = preg_replace('/[\t\r]\s+/', ' ', $compress);
		$compress = preg_replace('/<!(--)([^\[|\|])^(<!-->.*<!--.*-->)/', '', $compress);
		$compress = preg_replace('/\/\*.*?\*\//', '', $compress);
		return preg_replace("#\\s+#ism"," ",$compress);
	}
}
if ($e->name=='OnWebPagePrerender')
{
	$content = $modx->Event->params['documentOutput'];
	require_once MODX_BASE_PATH.'assets/lib/simple_html_dom.php';
	$html = new simple_html_dom();
	$html->load($content, false, null, -1, -1, true, true, DEFAULT_TARGET_CHARSET, false); 
	$title = $html->find('title',0);        
	$metaTitle = str_replace("'","'",$title->plaintext); // Чтобы не искать потом - заголовок страницы
	$imagealt = $metaTitle.' '.$image_name.' '; //в конце подставляется порядковый номер начиная с 1
	$atitle = $metaTitle.' '.$link_name.' '; //в конце подставляется порядковый номер начиная с 1
	if ($link_title=='true')
	{
		//Проставляем тэг title для ссылок
		$links = $html->find("a"); 
		$ln = 1;        
		foreach ($links as $key => $link)
		{
			if (!$link->title)
			{
				if ($link->plaintext) 
				{
					$t = str_replace('"',"'",trim(mb_substr($link->plaintext,0,50)));
					if (strlen($t)<10)
					{
						$link->title = $atitle.' '.$ln;
						$ln++;
					}
					else $link->title = $t;
				}
				else 
				{
					$link->title = $atitle.' '.$ln;
					$ln++;
				}
			}
		}
	}
	if ($circle_link=='true')
	{
		//Убираем циклические ссылки
		$url = substr($_SERVER['REQUEST_URI'], 1);
		if ($url)
		{	

			$cu =  $html->find("a[href='".$url."']"); 
			foreach($cu as $u) $u->href = null;
		}
	}
	if ($external_link=='true')
	{		
		//Закрываем внешние ссылки
		$outlink = $html->find("a[href^=http]");
		foreach($outlink as $ou)
		{
			if (strpos($ou->href, MODX_SITE_URL)===false) 
			{
				$h = str_replace('?','%3F',$ou->href);
				$h = str_replace('&','%26',$h);
				$ou->href = MODX_SITE_URL.''.$error_page.'?url='.$h;
			}						
		}		
	}	
	
	if ($img_alt=='true')	
	{
		//Проставляем тэг alt для картинок      
		$imgs = $html->find("img"); 
		$ln = 1;        
		foreach ($imgs as $key => $img)
		{
			if (!$img->alt) 
			{
				$img->alt = $imagealt.' '.$ln;
				$ln++;
			}
		}
	}
	if ($generate_tag=='true')
	{
		//Генерим ключевики по тэгам
		$mk = $html->find('meta[name=keywords]',0);
		if (count($mk))         
		{			
			$keywords = array();
			$keyws = $html->find($tags);
			foreach($keyws as $keyw) $keywords[]=$keyw->plaintext;
			$str = implode(',',$keywords);          
			$str = strip_tags($str);
			$str2 = preg_replace('/[^a-zA-Zа-яА-Я0-9\s]/ui', ' ',$str ); 
			$str2 = str_replace('  ',' ',$str2);
			$str2 = str_replace(PHP_EOL,' ',$str2);
			$arr = explode(' ',$str2);
			foreach($arr as $key=>$val) if ($val) $arr[$key] = mb_strtolower(trim($val),'utf-8');
			//Выбираем наиболее часто встречающиеся
			$array_words = array_count_values($arr);
			arsort($array_words);
			$out = array();
			foreach($array_words as $word => $val)
			{
				if (mb_strlen($word,'utf-8')>=$min) $out[]=$word;
				if (count($out)==$limit) break;
			}
			$mk->content = mb_substr($mk->content.','.implode(',',$out), 0, 200);
		}  
	}
	if ($favicon_generate=='true')
	{
		$fi='';
				
		$sizes = array('57x57','144x144','72x72','144x144','60x60','120x120','76x76','152x152');
		foreach ($sizes as $size)
		{
		$as = explode('x',$size);
		$fi.='<link rel="apple-touch-icon-precomposed" sizes="'.$size.'" href="'.$modx->runSnippet('phpthumb',array('input'=>$img,'options'=>'w='.$as[0].',h='.$as[1].',f=png,far=C,bg='.$bg)).'"/>'.PHP_EOL;		
		}

		//classic
		$sizes = array('32x32','16x16','96x96','128x128','196x196');
		foreach ($sizes as $size)
		{
		$as = explode('x',$size);
		$fi.='<link rel="icon" type="image/png" sizes="'.$size.'" href="'.$modx->runSnippet('phpthumb',array('input'=>$img,'options'=>'w='.$as[0].',h='.$as[1],',f=png,far=C,bg='.$bg)).'"/>'.PHP_EOL;
		}

		// MS
		$out.='<meta name="msapplication-TileColor" content="#FFFFFF" />'.PHP_EOL;
		$out.='<meta name="msapplication-TileImage" content="'.$modx->runSnippet('phpthumb',array('input'=>$img,'options'=>'w=144,h=144,f=png,far=C,bg='.$bg)).'" />'.PHP_EOL;
		$sizes = array('70x70','150x150','310x310');
		foreach ($sizes as $size)
		{
		$as = explode('x',$size);
		$fi.='<meta name="msapplication-square'.$size.'logo" content="'.$modx->runSnippet('phpthumb',array('input'=>$img,'options'=>'w='.$as[0].',h='.$as[1].',f=png,far=C,bg='.$bg)).'"/>'.PHP_EOL;
		}		
		$fi.='<link rel="shortcut icon" href="'.$modx->runSnippet('phpthumb',array('input'=>$img,'options'=>'w=16,h=16,f=ico,far=C,bg='.$bg)).'" type="image/x-icon">
		<link rel="icon" href="'.$modx->runSnippet('phpthumb',array('input'=>$img,'options'=>'w=16,h=16,f=ico,far=C,bg='.$bg)).'" type="image/x-icon">';
		$content = str_replace('</head>',$fi.'</head>',$content);
	}
	if ($one_line=='true')
	{		
		// Хак для того, чтобы не сжимать тэг <pre>     
		$pre = $html->find("pre"); 
		if (count($pre)) 
		{
			$arr_pre = array();
			foreach ($pre as $p) $arr_pre[] = $p->outertext;
		}		
		$content = compress_html($content); //Вытягиваем в 1-у строку
		// Хак для того, чтобы не сжимать тэг <pre>
		if (count($pre)) 
		{       
			preg_match_all('~<pre(.*?)pre>~s', $content, $matches);
			foreach ($matches[0] as $key => $pre) $content = str_replace($pre,$arr_pre[$key],$content);
		}
	}	
	$content = $html->save();
	$html->clear(); 	
	$modx->Event->output($content);
}
if ($e->name=='OnPageNotFound')
{

	$q = $modx->db->escape($_REQUEST['q']);	
	if (isset($_GET['url']) && (!empty($_GET['url'])) && ($q==$error_page))
	{
	$pos = strpos($_SERVER['HTTP_REFERER'], $_SERVER["HTTP_HOST"]);
		
		if ($pos)
		{	
			$url = $_GET['url'];
			if (!preg_match('#(https?|ftp)://\S+[^\s.,>)\];\'\"!?]#i',$url)) 
			{
				exit ("<p>Неверный формат запроса! Проверьте URL!</p>");
			} 
			else 
			{
				header("Location:$url");
				exit();
			}
		}
		else 
		{
			die('fuck eor, hacker fucking');
		}
	}
}       	
//i dont know what is psr
