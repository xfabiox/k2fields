<?php
//$Copyright$

// no direct access
defined('_JEXEC') or die('Restricted access');

class K2FieldsModuleHelper {
        public static function renderCategoryLabel($items, &$params, &$access, $imgOnly = false) {
                $item = $items[0];
                
                if (isset($item->tabName)) {
                        $name = $item->tabName;
                } else {
                        if (isset(self::$categoriesData) && isset(self::$categoriesData[$item->catid])) {
                                $name = self::$categoriesData[$item->catid][0][1];
                        }
                        
                        if (!isset($name)) $name = $item->categoryname;
                }
                
                $img = !empty($items->tabImage) ? $item->tabImage : $item->categoryimage;
                
                return 
                        $name .
                        (
                                $params->get('catImage') && !empty($img) && !$imgOnly ? 
                                '<img src="'.JURI::root().'media/k2/categories/'.$img.'" alt="'.$name.'" />' :
                                ''
                        )
                        ;
        }
        
	public static function renderCategory(&$items, &$params, &$access, $preTexts, $tabNo) {
                $componentParams = JComponentHelper::getParams('com_k2');
                $preText = self::renderPretext($preTexts, $tabNo);
		require JModuleHelper::getLayoutPath('mod_k2fields_contents', '_category');
	}
        
        private static $categories = array(), $categoryTree = array(), $categoryParents = array(), $categoriesData, $_items = array();
        
        private static function getCategoryChildren($ids, $excludeIds, $depth = -1, $clear = false) {
                static $currDepth = 0;
                
                if ($clear) {
                        self::$categories = array();
                        self::$categoryTree = array();
                        self::$categoryParents = array();
                        $currDepth = 0;
                }
                
                if (($depth != -1 && $currDepth > $depth) || empty($ids))
                        return self::$categories;
                
                $user = JFactory::getUser();
                $aid = (int) $user->get('aid');
                $db = JFactory::getDBO();
                $ids = (array) $ids;
                $ids = array_unique($ids);
                
                $query = "SELECT *, (SELECT COUNT(*) FROM #__k2_categories cc WHERE cc.parent = c.id AND cc.published=1 AND cc.trash=0 AND cc.access<={$aid}) AS cnt FROM #__k2_categories c WHERE c.parent IN (".(implode(", ", $ids)).") AND c.published=1 AND c.trash=0 AND c.access<={$aid}";
                
                if (!empty($excludeIds)) {
                        $excludeIds = (array) $excludeIds;
                        $query .= " AND c.id NOT IN (".implode(',', $excludeIds).")";
                }
                
                $query .= " ORDER BY c.ordering";
                
                $db->setQuery($query);
                $rows = $db->loadObjectList();
                $catsWithChildren = array();

                foreach ($rows as $row) {
                        self::$categories[$row->id] = $row;
                        
                        if (!isset(self::$categoryTree[$row->parent])) 
                                self::$categoryTree[$row->parent] = array();
                        
                        self::$categoryTree[$row->parent][] = $row->id;
                        self::$categoryParents[$row->id] = $row->parent;
                        
                        if ($row->cnt > 0) 
                                $catsWithChildren[] = $row->id;
                }
                
                $currDepth++;
                
                if (!empty($catsWithChildren)) 
                        self::getCategoryChildren($catsWithChildren, $excludeIds, $depth, false);
                
                return self::$categories;                
        }
        
        public static function getItemLayout($item, $ext, $extType, $layoutDir, $extLayoutDir) {
                $extDir = '/' . $extType . 's' . '/' . $ext;
                $tmpl = JFactory::getApplication()->getTemplate();
                
		$dirs = array(JPATH_SITE.'/templates/'.$tmpl.'/html/'.$layoutDir.'/', JPATH_SITE . $extDir . '/' . $extLayoutDir . '/');
                $tmpl = '';
                
                // In priority order
                $files = array('i'.$item->id.'.php', 'c'.$item->catid.'.php', 'item.php');
                
                foreach ($dirs as $dir) {
                        foreach ($files as $file) {
                                if (JFile::exists($dir.$file)) {
                                        $tmpl = $dir.$file;
                                        break;
                                }
                        }
                }
                
                return $tmpl;
        }
        
        public static function getList($params, $componentParams, $format = 'html', $partBy = 'category', $caller = 'mod_k2fields_contents') {
                $cache = JFactory::getCache($caller);
                $result = $cache->call('K2FieldsModuleHelper::_getList', $params, $componentParams, $format, $partBy); 
                return $result;
        }

        // Credit: modified version of corresponding fuction in mod_k2_content
	static function _getList($params, $componentParams, $format = 'html', $partBy = 'category') {
                $moduleId = $params->get('module_id');
                
                if ($moduleId && isset(self::$_items[$moduleId])) return self::$_items[$moduleId];
                
                $items = $params->get('items');
                $itemsProvided = !empty($items);
                
                if ($itemsProvided) {
                        if (!is_array($items)) $items = explode('|', $items);
                        
                        if (!is_numeric($items[0])) {
                                $items = self::prepareList($items, $params);
                                if ($moduleId) self::$_items[$moduleId] = $items;
                                return $items;
                        }
                } else {
                        $childrenMode = (int) $params->get('getChildren', 0);
                        $rootCategory = $params->get('catid');
                        $isStickyMenu = $rootCategory == 'stickymenu';
                        
                        if ($isStickyMenu) {
                                self::$categoriesData = JprovenUtility::getK2CategoriesSelector(
                                        2, 0, array(), false, '', false, '', true, false
                                );
                                
                                $rootCategory = (array) JprovenUtility::getColumn(self::$categoriesData, 0);
                                self::$categoriesData = JprovenUtility::indexBy(self::$categoriesData, array(0));
                        } else if (strpos($rootCategory, 'sticky') === 0) {
                                $option = JRequest::getCmd('option');
                                
                                if ($option != 'com_k2' && $option != 'com_k2fields') return;

                                $stickTo = str_replace('sticky', '', $rootCategory);
                                $view = JRequest::getCmd('view');
                                
                                if ($stickTo != 'cat' && $view != 'item') return;
                                
                                switch ($stickTo) {
                                        case 'cat':
                                                $rootCategory = null;

                                                if ($view == 'item') {
                                                        $itemId = JRequest::getInt('id');
                                                        require_once JPATH_SITE.'/components/com_k2/models/item.php';
                                                        $itemModel = JModel::getInstance('item', 'K2Model');
                                                        $item = $itemModel->getData();
                                                        $rootCategory = $item->catid;
                                                } else {
                                                        $rootCategory = JRequest::getInt('id', JRequest::getInt('cid', null));
                                                }

                                                if (empty($rootCategory)) return;
                                                
                                                break;
                                        case 'itemtag':
                                        case 'itemkeyword':
                                        case 'itemfield':
                                                $itemId = JRequest::getInt('id');
                                                require_once JPATH_SITE.'/components/com_k2/models/item.php';
                                                $itemModel = JModel::getInstance('item', 'K2Model');
                                                $item = $itemModel->getData();
                                                
                                                break;
                                }
                        }
                        
                        $excludeCategories = $params->get('excludecatids');

                        if (!empty($excludeCategories)) {
                                $excl = JprovenUtility::getK2CategoryChildren($excludeCategories, -1, true);
                                $excludeCategories = array_merge((array) $excludeCategories, $excl['cats']);
                        }

                        $depth = $childrenMode == 0 ? 1 : -1;
                        $cid = self::getCategoryChildren($rootCategory, $excludeCategories, $depth, true);
                        
                        if ($isStickyMenu && !empty($rootCategory)) {
                                if (!$cid) $cid = array();
                                foreach ($rootCategory as $rc) {
                                        if (!isset($cid[$rc])) {
                                                $cid[$rc] = '';
                                        }
                                }
                                $rootCategory = null;
                        }
                }
                
                $limit = $params->get('itemCountTotal', 0);
                $limitPerCat = $params->get('itemCount', 0);
                $ordering = $params->get('itemsOrdering','');
                $user = JFactory::getUser();
                $access = '.access IN (' . implode(',', $user->getAuthorisedViewLevels()) . ')';
                
                $db = JFactory::getDBO();
                $jnow = JFactory::getDate();
                $now = $jnow->toMySQL();
                $nullDate = $db->getNullDate();
                
                $query = "SELECT DISTINCT i.*, c.name AS categoryname, c.id AS categoryid, c.image AS categoryimage, c.alias AS categoryalias, c.alias AS catalias, c.params AS categoryparams";

                // Check if it's not the averaged amount that's already stored in items
                if ($ordering == 'best') $query .= ", (r.rating_sum/r.rating_count) AS rating";

                if ($ordering == 'comments') $query .= ", COUNT(comments.id) AS numOfComments";

                $queryFrom = " FROM #__k2_items as i LEFT JOIN #__k2_categories c ON c.id = i.catid";

                if ($ordering == 'best') $queryFrom .= " LEFT JOIN #__k2_rating r ON r.itemID = i.id";

                if ($ordering == 'comments') $queryFrom .= " LEFT JOIN #__k2_comments comments ON comments.itemID = i.id";

                $queryWhere = " WHERE i.published = 1 AND i{$access} AND i.trash = 0 AND c.published = 1 AND c{$access} AND c.trash = 0";
                $queryWhere .= " AND ( i.publish_up = ".$db->Quote($nullDate)." OR i.publish_up <= ".$db->Quote($now)." )";
                $queryWhere .= " AND ( i.publish_down = ".$db->Quote($nullDate)." OR i.publish_down >= ".$db->Quote($now)." )";

                if ($params->get('FeaturedItems') == '0')
                        $queryWhere .= " AND i.featured != 1";

                if ($params->get('FeaturedItems') == '2')
                        $queryWhere .= " AND i.featured = 1";

                if ($params->get('videosOnly'))
                        $queryWhere .= " AND (i.video IS NOT NULL AND i.video!='')";

                if ($ordering == 'comments')
                        $queryWhere .= " AND comments.published = 1";

                // TODO: Not supported yet, are order by rating
                switch ($ordering) {
                        case 'date':
                                $orderby = 'i.created ASC';
                                break;

                        case 'rdate':
                                $orderby = 'i.created DESC';
                                break;

                        case 'alpha':
                                $orderby = 'i.title';
                                break;

                        case 'ralpha':
                                $orderby = 'i.title DESC';
                                break;

                        case 'order':
                                if ($params->get('FeaturedItems') == '2')
                                        $orderby = 'i.featured_ordering';
                                else
                                        $orderby = 'i.ordering';
                                break;

                        case 'rorder':
                                if ($params->get('FeaturedItems') == '2')
                                        $orderby = 'i.featured_ordering DESC';
                                else
                                        $orderby = 'i.ordering DESC';
                                break;

                        case 'hits':
                                if ($params->get('popularityRange')){
                                        $datenow = &JFactory::getDate();
                                        $date = $datenow->toMySQL();
                                        $queryWhere .= " AND i.created > DATE_SUB('{$date}',INTERVAL ".$params->get('popularityRange')." DAY) ";
                                }
                                $orderby = 'i.hits DESC';
                                break;

                        case 'rand':
                                $orderby = 'RAND()';
                                break;

                        case 'best':
                                $orderby = 'i.rating DESC';
                                break;

                        case 'comments':
                                if ($params->get('popularityRange')){
                                        $datenow = &JFactory::getDate();
                                        $date = $datenow->toMySQL();
                                        $queryWhere .= " AND i.created > DATE_SUB('{$date}',INTERVAL ".$params->get('popularityRange')." DAY) ";
                                }
                                $queryWhere .= " GROUP BY i.id ";
                                $orderby = 'numOfComments DESC';
                                break;

                        case 'modified':
                                $orderby = 'i.modified DESC';
                                break;

                        default:
                                $orderby = 'i.id DESC';
                                break;
                }
                
                $timeRange = $params->get('timerange');
                $timeRangeField = $params->get('timerangefield');
                
                if (!empty($timeRange) && !empty($timeRangeField)) {
                        require_once JPATH_SITE.'/components/com_k2fields/models/searchterms.php';
                        $smModel = JModel::getInstance('searchterms', 'K2FieldsModel');
                        
                        require_once JPATH_ADMINISTRATOR.'/components/com_k2fields/models/fields.php';

                        $fieldsModel = JModel::getInstance('fields', 'K2FieldsModel');
                        $field = $fieldsModel->getFieldsById($timeRangeField);
                        $time = K2FieldsModelSearchterms::convertDates($timeRange, $field);
                        
                        if (!empty($time['start']) || !empty($time['end']))
                                $queryFrom .= ' INNER JOIN #__k2_extra_fields_values AS efv ON i.id = efv.itemid AND efv.fieldid = '.K2FieldsModelFields::value($field, 'id');

                        if (!empty($time['start']))
                                $queryWhere .= ' AND efv.datum >= '.$db->Quote($time['start']);

                        if (!empty($time['end']))
                                $queryWhere .= ' AND efv.datum <= '.$db->Quote($time['end']);
                }
                
                $query .= $queryFrom . $queryWhere;
                
                if (empty($items)) {
                        if (!empty($limitPerCat) || empty($limit)) {
                                if (empty($limitPerCat)) $limitPerCat = 5;

                                $cats = array_keys($cid);
                                $cnts = array_fill(0, count($cats), $limitPerCat);
                        } else {
                                $cats = array_keys($cid);
                                $cats = array($cats);
                                $cnts = array($limit);
                        }

                        $items = array();
                        $queries = array();

                        $efCriterias = $params->get('itemExtraFieldsCriterias');
                        $efc = array();
                        
                        if ($efCriterias) {
                                require_once JPATH_SITE.'/components/com_k2fields/models/searchterms.php';
                                $st = JModel::getInstance('searchterms', 'K2fieldsModel');
                                
                                $efCriterias = explode("\n", $efCriterias);
                                $efc = array();
                                
                                foreach ($efCriterias as &$efCriteria) {
                                        $efCriteria = explode('%%', $efCriteria);
                                        parse_str($efCriteria[1], $efCriteria[1]);
                                        $efCriteria[1] = $st->parseSearchTerms($efCriteria[1], true, 'terms');
                                        $q = '';
                                        $st->sqlJoin($q, $efCriteria[1], 'i');
                                        $efc[$efCriteria[0]] = $q;
                                }
                        }
                        
                        foreach ($cats as $c => $cat) {
                                if (!is_array($cat)) $cat = array($cat);
                                
                                $q = $query;
                                
                                foreach ($cat as $_cat) {
                                        if (isset($efc[$_cat])) {
                                                $q = str_replace(' WHERE ', $efc[$_cat].' WHERE ', $q);
                                        }
                                }

                                $sql = @implode(',', $cat);

                                $queries[] = "(" . $q . " AND i.catid IN ({$sql}) ORDER BY i.catid, " . $orderby . " LIMIT 0, ".$cnts[$c] . ")";
                        }
                        
                        $queries = implode(' UNION  ALL ', $queries);
                } else {
                        $queries = 
                                $query .
                                ' AND i.id IN ('.implode(',', $items).')'.
                                (!empty($limit) ? ' LIMIT 0, '.$limit : '');
                }
                
                $db->setQuery($queries);
                $items = $db->loadObjectList();
                
                if ($partBy == 'category') $indexBy = 'catid';
                else if ($partBy == 'author') $indexBy = 'created_by';
                else $indexBy = 'catid';
                
                $items = JprovenUtility::indexBy($items, $indexBy);
                
                if (!$itemsProvided && $childrenMode == 2 && isset($rootCategory)) {
                        $rootChildren = self::$categoryTree[$rootCategory];
                        $_items = array();
                        
                        foreach ($items as $c => $itemsPerCat) {
                                $pc = $c;
                                
                                while (!in_array($pc, $rootChildren)) {
                                        $pc = self::$categoryParents[$pc];
                                }
                                
                                if (!isset($_items[$pc])) 
                                        $_items[$pc] = array();
                                
                                foreach ($itemsPerCat as &$item) {
                                        $item->tabName = self::$categories[$pc]->name;
                                        $item->tabImage = self::$categories[$pc]->image;
                                }
                                
                                $_items[$pc] = array_merge($_items[$pc], $itemsPerCat);
                        }
                        
                        foreach ($_items as &$item) shuffle($item);
                        
                        $items = $_items;
                }
                
                $items = self::prepareList($items, $params, $componentParams, $format);
                
                if (empty($partBy)) $items = JprovenUtility::flatten($items);
                
                if ($moduleId) self::$_items[$moduleId] = $items;
                
                return $items;
	}
        
        public static function renderPretext($preTexts, $tabNo) {
                if (!isset($preTexts[$tabNo])) $tabNo = 'all';
                if (!isset($preTexts[$tabNo])) return '';
                return $preTexts[$tabNo];
        }
        
        public static function getPretexts($params) {
                $preTexts = array();
                
                $texts = $params->get('itemPreText');
                
                if ($texts) {
                        $tag = $params->get('itemPreTextTag', '');
                        $open = $close = '';
                        
                        if ($tag) {
                                $open = '<'.$tag.'>';
                                $close = '</'.$tag.'>';
                        }
                        
                        $texts = explode("\n", $texts);
                        
                        foreach ($texts as $text) {
                                $text = explode('%%', $text);
                                if ($text[0] != 'all') $text[0] = (int) $text[0] - 1;
                                $preTexts[$text[0]] = array($open.$text[1].$close);
                                if (count($text) > 2) $preTexts[$text[0]][] = $text[2];
                                if (count($text) > 3) $preTexts[$text[0]][] = $text[3];
                        }
                }
                
                return $preTexts;
        }
        
        public static function prepareList($items, $params, $cparams, $format) {
		if (count($items)) {
                        $model = JModel::getInstance('item', 'K2Model');
                        
                        require_once JPATH_SITE.'/components/com_k2/helpers/permissions.php';
                        
                        $limitstart = $params->get('limitstart');
                        
			foreach ($items as $catId => &$itemsPerCat) {
                                foreach ($itemsPerCat as &$item) {
                                        JprovenUtility::loadK2SpecificResources($catId, $item->id);
                                        
                                        //Clean title
                                        $item->title = JFilterOutput::ampReplace($item->title);

                                        //Images
                                        if ($params->get('itemImage')) {
                                                $date = JprovenUtility::createDate($item->modified);
                                                $timestamp = '?t='.$date->format('U');

                                                if (JFile::exists(JPATH_SITE.DS.'media'.DS.'k2'.DS.'items'.DS.'cache'.DS.md5("Image".$item->id).'_XS.jpg')){
                                                        $item->imageXSmall = JURI::base(true).'/media/k2/items/cache/'.md5("Image".$item->id).'_XS.jpg';
                                                        if($componentParams->get('imageTimestamp')){
                                                                $item->imageXSmall.=$timestamp;
                                                        }
                                                }

                                                if (JFile::exists(JPATH_SITE.DS.'media'.DS.'k2'.DS.'items'.DS.'cache'.DS.md5("Image".$item->id).'_S.jpg')){
                                                        $item->imageSmall = JURI::base(true).'/media/k2/items/cache/'.md5("Image".$item->id).'_S.jpg';
                                                        if($componentParams->get('imageTimestamp')){
                                                                $item->imageSmall.=$timestamp;
                                                        }
                                                }

                                                if (JFile::exists(JPATH_SITE.DS.'media'.DS.'k2'.DS.'items'.DS.'cache'.DS.md5("Image".$item->id).'_M.jpg')){
                                                        $item->imageMedium = JURI::base(true).'/media/k2/items/cache/'.md5("Image".$item->id).'_M.jpg';
                                                        if($componentParams->get('imageTimestamp')){
                                                                $item->imageMedium.=$timestamp;
                                                        }					    
                                                }

                                                if (JFile::exists(JPATH_SITE.DS.'media'.DS.'k2'.DS.'items'.DS.'cache'.DS.md5("Image".$item->id).'_L.jpg')){
                                                        $item->imageLarge = JURI::base(true).'/media/k2/items/cache/'.md5("Image".$item->id).'_L.jpg';
                                                        if($componentParams->get('imageTimestamp')){
                                                                $item->imageLarge.=$timestamp;
                                                        }	
                                                }

                                                if (JFile::exists(JPATH_SITE.DS.'media'.DS.'k2'.DS.'items'.DS.'cache'.DS.md5("Image".$item->id).'_XL.jpg')){
                                                        $item->imageXLarge = JURI::base(true).'/media/k2/items/cache/'.md5("Image".$item->id).'_XL.jpg';
                                                        if($componentParams->get('imageTimestamp')){
                                                                $item->imageXLarge.=$timestamp;
                                                        }
                                                }

                                                if (JFile::exists(JPATH_SITE.DS.'media'.DS.'k2'.DS.'items'.DS.'cache'.DS.md5("Image".$item->id).'_Generic.jpg')){
                                                        $item->imageGeneric = JURI::base(true).'/media/k2/items/cache/'.md5("Image".$item->id).'_Generic.jpg';
                                                        if($componentParams->get('imageTimestamp')){
                                                                $item->imageGeneric.=$timestamp;
                                                        }	
                                                }

                                                $image = 'image'.$params->get('itemImgSize','Small');
                                                if (isset($item->$image))
                                                        $item->image = $item->$image;
                                        }
                                        
                                        //Read more link
                                        $item->link = urldecode(JRoute::_(K2FieldsHelperRoute::getItemRoute($item->id.':'.urlencode($item->alias), $item->catid.':'.urlencode($item->categoryalias))));

                                        //Tags
                                        if ($params->get('itemTags')) {
                                                $tags = $model->getItemTags($item->id);
                                                for ($i = 0; $i < sizeof($tags); $i++) {
                                                        $tags[$i]->link = JRoute::_(K2FieldsHelperRoute::getTagRoute($tags[$i]->name));
                                                }
                                                $item->tags = $tags;
                                        }

                                        //Category link
                                        if ($params->get('itemCategory'))
                                        $item->categoryLink = urldecode(JRoute::_(K2FieldsHelperRoute::getCategoryRoute($item->catid.':'.urlencode($item->categoryalias))));

                                        //Extra fields
                                        if ($params->get('itemExtraFields')) {
                                                $item->extra_fields = $model->getItemExtraFields($item->extra_fields);
                                        }

                                        //Comments counter
                                        if ($params->get('itemCommentsCounter'))
                                        $item->numOfComments = $model->countItemComments($item->id);

                                        //Attachments
                                        if ($params->get('itemAttachments'))
                                        $item->attachments = $model->getItemAttachments($item->id);

                                        //Import plugins
                                        if ($format != 'feed') {
                                                $dispatcher = &JDispatcher::getInstance();
                                                JPluginHelper::importPlugin('content');
                                        }

                                        //Video
                                        if ($params->get('itemVideo') && $format != 'feed') {
                                                $params->set('vfolder', 'media/k2/videos');
                                                $params->set('afolder', 'media/k2/audio');
                                                $item->text = $item->video;
                                                $dispatcher->trigger('onPrepareContent', array(&$item, &$params, $limitstart));
                                                $item->video = $item->text;
                                        }
                                        
                                        $item->date = JHtml::_('date', $item->created, JText::_('DATE_FORMAT_LC2'));

                                        // Introtext
                                        $item->text = '';
                                        if ($params->get('itemIntroText')) {
                                                // Word limit
                                                if ($params->get('itemIntroTextWordLimit')) {
                                                        $item->text .= K2HelperUtilities::wordLimit($item->introtext, $params->get('itemIntroTextWordLimit'));
                                                } else {
                                                        $item->text .= $item->introtext;
                                                }
                                        }
                                        
                                        if ($format != 'feed') {
                                                $params->set('parsedInModule', 1); // for plugins to know when they are parsed inside this module
                                                $item->params = new JParameter($item->params);
                                                $item->params->merge($params);

                                                if($params->get('JPlugins', 1)){
                                                        //Plugins
                                                        $results = $dispatcher->trigger('onBeforeDisplay', array(&$item, &$params, $limitstart));
                                                        $item->event->BeforeDisplay = trim(implode("\n", $results));

                                                        $results = $dispatcher->trigger('onAfterDisplay', array(&$item, &$params, $limitstart));
                                                        $item->event->AfterDisplay = trim(implode("\n", $results));

                                                        $results = $dispatcher->trigger('onAfterDisplayTitle', array(&$item, &$params, $limitstart));
                                                        $item->event->AfterDisplayTitle = trim(implode("\n", $results));

                                                        $results = $dispatcher->trigger('onBeforeDisplayContent', array(&$item, &$params, $limitstart));
                                                        $item->event->BeforeDisplayContent = trim(implode("\n", $results));

                                                        $results = $dispatcher->trigger('onAfterDisplayContent', array(&$item, &$params, $limitstart));
                                                        $item->event->AfterDisplayContent = trim(implode("\n", $results));

                                                        $dispatcher->trigger('onPrepareContent', array(&$item, &$params, $limitstart));
                                                        $item->introtext = $item->text;
                                                } else {
                                                        $item->event->BeforeDisplay = '';
                                                        $item->event->AfterDisplay = '';
                                                        $item->event->AfterDisplayTitle = '';
                                                        $item->event->BeforeDisplayContent = '';
                                                        $item->event->AfterDisplayContent = '';
                                                }

                                                //Init K2 plugin events
                                                $item->event->K2BeforeDisplay = '';
                                                $item->event->K2AfterDisplay = '';
                                                $item->event->K2AfterDisplayTitle = '';
                                                $item->event->K2BeforeDisplayContent = '';
                                                $item->event->K2AfterDisplayContent = '';
                                                $item->event->K2CommentsCounter = '';

                                                if($params->get('K2Plugins', 1)){
                                                        //K2 plugins
                                                        JPluginHelper::importPlugin('k2');
                                                        $results = $dispatcher->trigger('onK2BeforeDisplay', array(&$item, &$params, $limitstart));
                                                        $item->event->K2BeforeDisplay = trim(implode("\n", $results));

                                                        $results = $dispatcher->trigger('onK2AfterDisplay', array(&$item, &$params, $limitstart));
                                                        $item->event->K2AfterDisplay = trim(implode("\n", $results));

                                                        $results = $dispatcher->trigger('onK2AfterDisplayTitle', array(&$item, &$params, $limitstart));
                                                        $item->event->K2AfterDisplayTitle = trim(implode("\n", $results));

                                                        $results = $dispatcher->trigger('onK2BeforeDisplayContent', array(&$item, &$params, $limitstart));
                                                        $item->event->K2BeforeDisplayContent = trim(implode("\n", $results));

                                                        $results = $dispatcher->trigger('onK2AfterDisplayContent', array(&$item, &$params, $limitstart));
                                                        $item->event->K2AfterDisplayContent = trim(implode("\n", $results));

                                                        $dispatcher->trigger('onK2PrepareContent', array(&$item, &$params, $limitstart));
                                                        $item->introtext = $item->text;

                                                        if ($params->get('itemCommentsCounter')) {
                                                                $results = $dispatcher->trigger('onK2CommentsCounter', array ( & $item, &$params, $limitstart));
                                                                $item->event->K2CommentsCounter = trim(implode("\n", $results));
                                                        }
                                                } else {
                                                        $item->event->K2BeforeDisplay = '';
                                                        $item->event->K2AfterDisplay = '';
                                                        $item->event->K2AfterDisplayTitle = '';
                                                        $item->event->K2BeforeDisplayContent = '';
                                                        $item->event->K2AfterDisplayContent = '';
                                                }
                                        }

                                        //Clean the plugin tags
                                        $item->introtext = preg_replace("#{(.*?)}(.*?){/(.*?)}#s", '', $item->introtext);

                                        //Author
                                        if ($params->get('itemAuthor')) {
                                                if (! empty($item->created_by_alias)) {
                                                        $item->author = $item->created_by_alias;
                                                        $item->authorGender = NULL;
                                                        if ($params->get('itemAuthorAvatar'))
                                                        $item->authorAvatar = K2HelperUtilities::getAvatar('alias');
                                                } else {
                                                        $author = &JFactory::getUser($item->created_by);
                                                        $item->author = $author->name;
                                                        $query = "SELECT `gender` FROM #__k2_users WHERE userID=".(int)$author->id;
                                                        $db = JFactory::getDBO();
                                                        $db->setQuery($query, 0, 1);
                                                        $item->authorGender = $db->loadResult();
                                                        if ($params->get('itemAuthorAvatar')) {
                                                                $item->authorAvatar = K2HelperUtilities::getAvatar($author->id, $author->email, $cparams->get('userImageWidth'));
                                                        }
                                                        //Author Link
                                                        $item->authorLink = JRoute::_(K2FieldsHelperRoute::getUserRoute($item->created_by));
                                                }
                                        }
                                        
                                        if (is_array($item->extra_fields)) {
                                                foreach($item->extra_fields as $key => $extraField) {
                                                        if($extraField->type == 'textarea' || $extraField->type == 'textfield') {
                                                                $tmp = new JObject();
                                                                $tmp->text = $extraField->value;
                                                                if($params->get('JPlugins',1)){
                                                                        if(K2_JVERSION == '16') {
                                                                                $dispatcher->trigger('onContentPrepare', array ('mod_k2_content', &$tmp, &$params, $limitstart));
                                                                        }
                                                                        else {
                                                                                $dispatcher->trigger('onPrepareContent', array ( & $tmp, &$params, $limitstart));
                                                                        }
                                                                }
                                                                if($params->get('K2Plugins',1)){
                                                                        $dispatcher->trigger('onK2PrepareContent', array ( & $tmp, &$params, $limitstart));
                                                                }
                                                                $extraField->value = $tmp->text;
                                                        }
                                                }
                                        }                                        
                                }
			}
		}
                
		return $items;
        }
}
