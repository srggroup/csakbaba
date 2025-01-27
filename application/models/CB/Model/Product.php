<?php
namespace CB\Model;

use Zend_Registry;

class Product extends \CB_Resource_Model{


	protected function _buildOptions($options){
		parent::_buildOptions($options);

		if(!$this->ext){
			$this->qb->addOr($this->qb->expr()->field('deleted')->exists(false));
			$this->qb->addOr($this->qb->expr()->field('deleted')->equals(false));
		}
	}

	public function afterFind($items){
		foreach((is_array($items) ? $items : array()) as $key=>$item){
			if($item->deleted && !$this->ext){
				unset($items[$key]);
			}
		}
		return $items;
	}

    public function runQuery()
    {
        if(!$this->ext){
            $this->qb->field('deleted')->notEqual(true);
        }
        return parent::runQuery();
    }

    public function extAfterFind($items, $params=array()){
		if(isset($params['single'])){ $items=array($items); }
		foreach($items as $key=>$item){
			if($items[$key]->user) $items[$key]->user->get();
		}
		$items=array_values($items);
		if(isset($params['single'])){ $items=$items[0]; }
		return parent::extAfterFind($items, $params);
	}

	public function extBeforeSave($items, $params){
		if(isset($params['single'])){ $items=array($items); }
		foreach($items as $key=>$item){
			if(is_array($items[$key]->user) && array_key_exists('__isInitialized__', $items[$key]->user)){
				$userModel=new \CB\Model\User();
				$items[$key]->user=$userModel->findOneById($items[$key]->user['id']);
			}
		}
		if(isset($params['single'])){ $items=$items[0]; }
		return parent::extBeforeSave($items, $params);
	}

	function findByCategory($category, $tree){
		//if(!$category) return array();
		$this->initQb();
		if(!$category){

		} else if(!empty($category->children)){
			$this->qb->field('category')->equals(new \MongoDb\BSON\Regex('/^'.$category->id.'\-.*/i'));
		} else {
			$this->qb->field('category')->equals($category->id);
		}
		$this->qb->field('status')->equals(1);

		$sort=!empty($_POST['sort']) ? explode('-', $_POST['sort']) : array('date_added', 'desc');
		$this->qb->sort($sort[0], $sort[1]);

		if($tree && !empty($_POST)){
			$options=$category->props;
			$props=\Zend_Registry::get('categories')->_props;
			foreach($options as $id){
				$o=$props[$id];
				if(array_key_exists($o['slug'], $_POST)){
					switch($o['type']){
						case 'select':
							$this->qb->field('options.'.$id)->in($_POST[$o['slug']]);
							break;
						case 'number':
							$exploded = explode('-', $_POST[$o['slug']]);
							$range=range(reset($exploded), end($exploded));
							$range=array_map(function($a){ return strval($a);	}, $range);
							$this->qb->field('options.'.$id)->in($range);
							//$this->qb->field('options.'.$id)->equals(new \MongoDb\BSON\Regex('/['.implode(',',$range).']{1}/i'));
							break;
						default: break;
					}
				}
			}

			$genreArray=array();
			foreach(\Zend_Registry::get('genreTypes') as $slug=>$type){
				if(array_key_exists($slug, $_POST) && $_POST[$slug]==1) $genreArray[]=$slug;
			}
			if(!empty($genreArray)) $this->qb->field('type')->in($genreArray);

		}
		$results=$this->runQuery();
		if(empty($_POST['sort'])){
			$promoted=$this->getPromoted('list');
			foreach($promoted as $p){
				if(array_key_exists($p->id, $results)) $results=array($p->id => $results[$p->id]) + $results;
			}
		}

		$page=!empty($_GET['page']) ? $_GET['page'] : 1;
		$pageSize=15;
		if(!empty($_COOKIE['resolution'])){
			$res=$_COOKIE['resolution'];
			foreach(self::$rowSizes as $limit=>$rs){
				if($res >= $limit) $pageSize=3*$rs;
			}
		}
		header('X-CSB-PRC: '.count($results));
		\Zend_Registry::set('productsCount', count($results));
		$results=array_slice($results, ($page-1)*$pageSize, $pageSize);
		return $results;
	}

	private function _getIds($value, $option){
		switch($option['type']){
			case 'select':
				foreach($option['children'] as $child){
					if(($key=array_search($child['slug'], $value))!==false) $value[$key]=$child['id']->__toString();
				}
				break;
			default: break;
		}
		return $value;
	}

	public function getMostVisited(){
		if(!($visited=$this->cache->load('mainVisited'))){
			$visited=$this->find(array('conditions'=>array('status'=>1), 'order'=>'visitors desc', 'limit'=>12));
			$this->cache->save($visited, 'mainVisited', array(), 120);
		}
		return $visited;
	}

	public function getFresh(){
		if(!($fresh=$this->cache->load('mainFresh'))){
			$this->initQb();
			$this->qb->field('status')->equals(1);
			if (Zend_Registry::get('CsbConfig')->get('hideC2Orders')) {
				$this->qb->field('user')->notEqual(new \MongoDB\BSON\ObjectId('528a82320f435fd2028b4568'));
			}
			$this->qb->sort('date_added', 'desc');
			$this->qb->limit(12);
			$fresh=$this->runQuery();
			$this->cache->save($fresh, 'mainFresh', array(), 120);
		}
		return $fresh;
	}

	public function getRandom($count = 10){
		//if(!($fresh=$this->cache->load('mainFresh'))){
		$fields=array_keys($this->repository->getClassMetadata()->reflFields);
		$random=$this->initQb();
		$this->qb->field('status')->equals(1);
		if (Zend_Registry::get('CsbConfig')->get('hideC2Orders')) {
			$this->qb->field('user')->notEqual(new \MongoDB\BSON\ObjectId('528a82320f435fd2028b4568'));
		}
		$this->qb->addOr($this->qb->expr()->field('deleted')->exists(false));
		$this->qb->addOr($this->qb->expr()->field('deleted')->equals(false));
		$ascdesc=['desc', 'asc'];
		$this->qb->sort($fields[array_rand($fields)], $ascdesc[array_rand($ascdesc)]);
		$this->qb->limit($count);
		$random=$this->runQuery();
			//$this->cache->save($random, 'mainFresh', array(), 120);
		//}
		return $random;
	}

	public function getFavouriteLists(){
		$userModel=new \CB\Model\User();
		$userModel->initQb();
		$userModel->qb->field('favourites')->exists(true);
		$userModel->qb->where('this.favourites.length > 0');
		//$userModel->qb->field('favourites')->not($userModel->qb->expr()->size(0));
		//$userModel->qb->field('favourties')->equals('function() { this.length > 0; }');
		$users=$userModel->runQuery();
		shuffle($users);
		$user=reset($users);
		return $user ? $user->favourites : array();
	}

	public function getPromoted($type, $randomize=true, $key=''){
		if(!($products=$this->cache->load('promoted_'.$type.'_'.($randomize?1:0).'_'.$key))){
			$this->initQb();
			$this->qb->field('promotes.'.$type)->gte(time());
			$this->qb->field('status')->equals(1);
			if(!empty($key)) $this->qb->field('category')->equals(new \MongoDb\BSON\Regex('/^'.$key.'-.*/iu'));
			//$this->qb->field('promotes.'.$type)->lte(strtotime('-1 weeks'));
			$products=$this->runQuery();
			shuffle($products);
			$products=$products ? $products : array();

			$this->cache->save($products,'promoted_'.$type.'_'.($randomize?1:0).'_'.$key, array(), 120);
		}
		return $products;
	}


	public function search($q='', $categoryId=false){
		$this->initQb();
		if($categoryId) $this->qb->field('category')->equals($categoryId);
		foreach(explode(' ', $q) as $word){
			$word=trim($word);
			if(empty($word)) continue;
			$this->qb->field('name')->equals(new \MongoDb\BSON\Regex('/.*'.$word.'.*/iu'));
		}
		$this->qb->field('status')->equals(1);
		$resultName=$this->runQuery();
		$this->initQb();
		foreach(explode(' ', $q) as $word){
			$word=trim($word);
			if(empty($word)) continue;
			$this->qb->field('desc')->equals(new \MongoDb\BSON\Regex('/.*'.htmlentities($word, ENT_COMPAT | 'ENT_HTML401', 'UTF-8').'.*/iu'));
		}
		$this->qb->field('status')->equals(1);
		$resultDesc=$this->runQuery();

		$results=array();
		foreach($resultName as $rn){
			$results[$rn->id]=array('point'=>5, 'product'=>$rn);
		}
		foreach($resultDesc as $rd){
			if(!array_key_exists($rd->id, $results)){
				$results[$rd->id]=array('point'=>1, 'product'=>$rd);
			} else {
				$results[$rd->id]['point']++;
			}
		}

		usort($results, function($a,$b){
			return $a['point']<$b['point'] ? 1 : -1;
		});
		return $results;
	}


	static $rowSizes=array(
		0=>1,
		401=>2,
		801=>3,
		961=>4,
		1181=>5,
		1601=>7
	);
    
	static $smallRowSizes=array(
		0=>2,
		401=>3,
		801=>4,
		961=>5,
		1181=>6,
		1601=>7
	);

}
