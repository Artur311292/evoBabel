/**
 * evoBabelPlaceholder
 *
 * plugin for work evoBabel (use placeholder [%key%] for language versions)
 *
 * @author	    webber (web-ber12@yandex.ru)
 * @category	plugin
 * @version	    0.1
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal	@guid 223453636a8c613426979b9dea1ff0415abf
 * @internal    @events OnParseDocument
 * @internal    @properties 
 * @internal    @installset MultiLang
 * @internal	@modx_category Manager and Admin
 */


$e =& $modx->event;
switch ($e->name ) {
    case 'OnParseDocument':
	//	обрабатываем языковые плейсхолдеры вида [%ключ%]
		$source = $modx->documentOutput;
        $source = $this->mergeSettingsContent($source);
        $source= $this->mergeDocumentContent($source);
        $source = $this->mergeSettingsContent($source);
        $source= $this->mergeChunkContent($source);
	
		$pattern="/\[%[^%\]](.*)%\]/";
	
		preg_match_all($pattern,$source, $matches, PREG_PATTERN_ORDER);
		if(is_array($matches[0])){
			foreach ($matches[0] as $v){
				$k=str_replace('%]','',str_replace('[%','',$v));
				if(isset($_SESSION['perevod'][$k])){
					$source=str_replace($v,$_SESSION['perevod'][$k],$source);
				}
			}
		}
		$modx->documentOutput = $source;
		break;
	
    default:
        return ;
}