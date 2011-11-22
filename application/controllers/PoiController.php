<?php

/**
 * Controller for requesting POIs and POIs sets using AJAX
 */

class PoiController extends Zend_Controller_Action
{

    protected $_serviceModels = array();

    public function init()
    {
        foreach(Zend_Registry::get('var')->services as $serviceId => $service) {
            $classname = $service['model'];
            $this->_serviceModels[$serviceId] = new $classname();
        }
        
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        
        $ajaxContext->addActionContext('get-nearby', 'json')
                    ->initContext();
    }

    public function getNearbyAction()
    {
        $lat = (double) $this->_getParam('lat');
        $long = (double) $this->_getParam('long');
        $radius = (int) $this->_getParam('radius');
        $term = (string) $this->_getParam('term');
        
        // lat and long params are mandatory
        if (empty($lat) || empty($long) || !is_numeric($lat) || !is_numeric($long)) {
            return;
        }
        
        $pois_raw = array();
        
        foreach ($this->_serviceModels as $modelId => $model) { // iterate through availabe models
            if ((boolean) $this->_getParam($modelId)) { // use service
                $pois_raw = array_merge(
                        $pois_raw,
                        $model->getNearbyVenues($lat, $long, $radius, $term));
            }
        }

        if (count($pois_raw) > 0) {
            //$this->view->pois = $pois;
            $pois = $this->_mergePois($pois_raw);
            $this->view->pois = array();
            $i = 0;
            foreach ($pois as $poi) {
                $this->view->pois[$i]['name']   = $poi->getName();
                $this->view->pois[$i]['id']     = $poi->getId();
                $this->view->pois[$i]['types']  = $poi->getTypes();
                $this->view->pois[$i]['lat']    = $poi->getLat();
                $this->view->pois[$i]['lng']    = $poi->getLng();
                $this->view->pois[$i]['distance'] = $poi->getDistance();
                $this->view->pois[$i]['address'] = $poi->getAddress();                
                $i++;
            }
            
        }
        
        // overwrite context setting for testing purposes // TODO
        //$response = $this->getResponse();
        //$response->setHeader('Content-Type', 'text/html');
    }
    
    public function testAction() {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        
        $lat = (double) $this->_getParam('lat');
        $long = (double) $this->_getParam('long');
        $radius = (int) $this->_getParam('radius');
        $term = (string) $this->_getParam('term');
        $service['fq'] = (boolean) $this->_getParam('fq');
        $service['gw'] = (boolean) $this->_getParam('gw');
        $service['gg'] = (boolean) $this->_getParam('gg');
        $service['fb'] = (boolean) $this->_getParam('fb');
        
        //$model = new GSAA_Model_LBS_GooglePlaces();        
        //print_r($model->getNearbyVenues($lat, $long, $radius, $term));
        $pois_raw = array();
        foreach ($this->_serviceModels as $modelId => $model) { // iterate through availabe models
            if ((boolean) $service[$modelId] ) { // use service
                $pois_raw = array_merge(
                        $pois_raw,
                        $model->getNearbyVenues($lat, $long, $radius, $term));
            }
        }      
        
        $pois = $this->_mergePois($pois_raw);
                
        print_r($pois);
    }
    
    
    /**
     * Merge array of GSAA_Model_POI
     * 
     * @param array $pois_raw
     * @return array Array of GSAA_Model_AggregatedPOI
     */    
    protected function _mergePois(array $pois_raw) {                
        $pois = array(); // Array of GSAA_Model_AggregatedPOI
        for ($x = 0; $x < count($pois_raw); $x++) {
            if (is_null($pois_raw[$x])) continue; // skip already merged items
            $agPoi = new GSAA_Model_AggregatedPOI();
            $agPoi->addPoi($pois_raw[$x]); // copy entire POI
            
            for ($y = 0; $y < count($pois_raw); $y++) {
                if (is_null($pois_raw[$y])) continue; // skip already merged items
                if ($x == $y) continue; // skip the same POI
                
                $similar_percent = 0;
                /* TODO: convert name to ASCII, to get better results
                 * Test case: 50.076738, 14.5; Strasnicke divadlo
                 */
                /*
                 * TODO: other text matching improvenets (maybe remove some chars,
                 * divide name on parts dividers like | and (),
                 * remove common prefixes like "Restaurace" [but then be more strict on distance] etc.)
                 */
                
                $poiXName = strtolower($pois_raw[$x]->name);
                $poiYName = strtolower($pois_raw[$y]->name);
                
                similar_text($poiXName, $poiYName, $similar_percent);
                
                $distance = $this->_serviceModels[$pois_raw[$x]->type]->getDistance(
                                $pois_raw[$x]->lat,
                                $pois_raw[$x]->lng,
                                $pois_raw[$y]->lat,
                                $pois_raw[$y]->lng);
                
                /*echo "&nbsp;&nbsp;&nbsp;&nbsp;" . $y . ": " . $pois_raw[$y]->name . " | "
                        . 'similar_text: ' . round($similar_percent, 1) . " | "
                        . 'distance: '
                        . $distance
                        . "<br />\n";*/
                if ($similar_percent > 75 && $distance < 150) { // Merge objects
                    
                    $agPoi->addPoi($pois_raw[$y]); // copy entire POI
                    $pois_raw[$y] = null; // remove content from array, so that the POI wont be merged again
                    // TODO: is it wise, just tu find similarities between the first one? Maybe find all pairs and sorty by similarity?
                } 
            }
            $pois[] = $agPoi;
        }
        return $pois;
    }


}



