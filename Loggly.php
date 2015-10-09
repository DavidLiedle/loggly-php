<?php
/**
 * Copyright 2012, 2015 Loggly Inc.
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 * http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @link https://github.com/loggly/loggly-php
 *
 */

class LogglyException extends Exception {}

class Loggly {
    
    /**
     * @var string The domain name
     */
    public $domain    = 'loggly.com';
    
    /**
     * @var string Proxy domain name
     */
    public $proxy     = 'logs.loggly.com';
    
    /**
     * @var string Your Loggly account's subdomain, ex. http://thispart.loggly.com/
     */
    public $subdomain = '';
    
    /**
     * @var string Your Loggly username
     */
    public $username  = '';
    
    /**
     * @var string Your Loggly account password
     */
    public $password  = '';
    
    /**
     * @var array 
     */
    public $inputs    = array();

    public function __construct() {}

    /**
     * makeRequest
     * 
     * @param string $path
     * @param type $params
     * @param type $method
     * 
     * @return type
     * 
     * @throws LogglyException
     */
    public function makeRequest( $path, $params = null, $method = 'GET' ){
        
        // maintain compatibility with Python Hoover library
        if( $path[0] !== '/' ){
            $path = '/' . $path;
        } // End of if

        $method = strtoupper($method);
        $url = sprintf('https://%s.%s%s', $this->subdomain, $this->domain, $path);
        $curl = curl_init();

        if( $method === 'POST' ){
            curl_setopt($curl, CURLOPT_POST, 1);
        } // End of if

        if( $method === 'PUT' || $method === 'DELETE' ){
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        } // End of if

        // set HTTP headers
        curl_setopt($curl, CURLOPT_USERPWD,        $this->username . ':' . $this->password);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    
        // handle URL params
        if( $params ){
            
            $segments = array();
            foreach ($params as $k => $v) {
                $segments[] .= $k . '=' . urlencode($v);
            }

            $qs = join($segments, '&');

            if ($method === 'POST' || $method === 'PUT') {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $qs);
            } else {
                $url .= '?' . $qs;
            }

        } // End of if( $params )

        curl_setopt($curl, CURLOPT_URL, $url);

        // satisfy content length header requirement for PUT
        if( $method === 'PUT' ){
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Length: ' . strlen($qs))); 
        } // End of if

        $result = curl_exec($curl);

        if( !$result ){
            throw new LogglyException(curl_error($curl));
        } // End of if

        $json = json_decode($result, true);
        
        if( !$json ){
            
            // API is inconsistent
            $status = curl_getinfo($curl, CURLINFO_HTTP_CODE); 
            if( $status >= 200 && $status <= 299 ){
                return null;
            } // End of if
            curl_close($curl);
            throw new LogglyException($result);
            
        } // End of if

        curl_close($curl);
        return $json;
        
    } // End of public function makeRequest

    /**
     * getInputs
     * @return string
     */
    public function getInputs(){
        return $this->makeRequest('/api/inputs');
    } // End of public function getInputs()

    /**
     * getDevices
     * @return string
     */
    public function getDevices() {
        return $this->makeRequest('/api/devices');
    } // End of public function getDevices()

    ###################################################### input-related methods

    /**
     * addDevice
     * 
     * @param type $inputId
     * 
     * @return type
     * 
     */
    public function addDevice( $inputId ){
        return $this->makeRequest('/api/inputs/' . $inputId . '/adddevice', null, 'POST');
    } // End of public function addDevice( $inputId )

    /**
     * removeDevice
     * 
     * @param type $inputId
     * 
     * @return type
     * 
     */
    public function removeDevice( $inputId ){
        return $this->makeRequest('/api/inputs/' . $inputId . '/removedevice', null, 'POST');
    } // End of public function removeDevice( $inputId )

    /**
     * enableDiscovery
     * 
     * @param type $inputId
     * 
     * @return type
     * 
     */
    public function enableDiscovery( $inputId ){
        return $this->makeRequest('/api/inputs/' . $inputId . '/discover', null, 'POST');
    } // End of public function enableDiscovery( $inputId )

    /**
     * disableDiscovery
     * 
     * @param type $inputId
     * 
     * @return type
     * 
     */
    public function disableDiscovery( $inputId ){
        return $this->makeRequest('/api/inputs/' . $inputId . '/discover', null, 'DELETE');
    } // End of public function disableDiscovery( $inputId )

    ##################################################### search-related methods

    /**
     * search
     * 
     * @param type $q
     * @param array $params
     * 
     * @return type
     * 
     */
    public function search( $q, $params = null ){
        
        $params['q'] = $q;
        
        return $this->makeRequest('/api/search', $params);
        
    } // End of public function search( $q, $params = null )

    /**
     * facet
     * 
     * @param type $q
     * @param type $facet
     * @param array $params
     * 
     * @return string
     * 
     */
    public function facet( $q, $facet = 'date', $params = null ){
        
        $params['q'] = $q;
        
        return $this->makeRequest('/api/facets/' . $facet, $params);
        
    } // End of public function facet( $q, $facet = 'date', $params = null )

    /**
     * getSavedSearches - Make an API request to get searches saved on Loggly
     * @return type
     * 
     */
    public function getSavedSearches() {
        return $this->makeRequest('/api/savedsearches/');
    } // End of public function getSavedSearches()

    /**
     * createSavedSearch
     * 
     * $params is an array with keys 'foo' and 'context'
     * context is a JSON blob, e.g.
     * $params = array('name'    => 'foo',
     *                 'context' => '{
     *                                  "search_type": "search",
     *                                  "terms":       "error AND 500",
     *                                  "from":        "NOW-1HOUR",
     *                                  "until":       "NOW",
     *                                  "inputs":      [
     *                                                  "app",
     *                                                  "staging"
     *                                                 ]
     *                               }'
     *                );
     * 
     * @param type $params
     * 
     * @return type
     * 
     */
    public function createSavedSearch( $params ){
        return $this->makeRequest('/api/savedsearches', $params, 'POST');
    } // End of public function createSavedSearch( $params )

    /**
     * updateSavedSearch
     * 
     * @param array $params $params must contain a key 'id'
     * 
     * @return type
     * 
     */
    public function updateSavedSearch( $params ){
        return $this->makeRequest('/api/savedsearches', $params, 'PUT');
    } // End of public function updateSavedSearch( $params )

    /**
     * deleteSavedSearch
     * 
     * @param type $id
     * 
     * @return type
     * 
     */
    public function deleteSavedSearch( $id ){
        return $this->makeRequest('/api/savedsearches/' . $id, null, 'DELETE');
    } // End of public function deleteSavedSearch( $id )

    /**
     * runSavedSearch
     * 
     * @param type $id
     * @param type $facet
     * @param type $facetBy
     * 
     * @return type
     * 
     */
    public function runSavedSearch( $id, $facet = false, $facetBy = 'date' ){
        
        if( !$facet ){
            
            return $this->makeRequest('/api/savedsearches/' . $id . '/results');
            
        } else {
            
            return $this->makeRequest('/api/savedsearches/' . $id . '/facets/' . $facetBy);
            
        } // End of if( !$facet ) / else
        
    } // End of public function runSavedSearch( $id, $facet = false, $facetBy = 'date' )


    #################################################### account-related methods

    /**
     * getCustomer
     * 
     * @return type Always returns current Loggly account
     * 
     */
    public function getCustomer(){
        
        return $this->makeRequest('/api/customers/');
        
    } // End of public function getCustomer()

    /**
     * getCustomerStats
     * 
     * @return type Always returns current Loggly account
     * 
     */
    public function getCustomerStats(){
        
        return $this->makeRequest('/api/customers/stats');
        
    } // End of public function getCustomerStats()
    
} // End of class Loggly
