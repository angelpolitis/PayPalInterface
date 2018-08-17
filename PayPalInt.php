<?php
	# The PayPalInterface class.
	class PayPalInterface {
        # A private counter used to assign a unique id to each instance of the class.
		private static $counter = 0;
        
        # A private variable that contains the default settings of the class.
        private static $default_settings = ["log_path" => "ipn.log", "sandbox" => false];
        
        # A private variable containing an array specific to paypal products with number.
        private static $form_prod_num_props = [
            "item_name" => null,
            "item_number" => null,
            "quantity" => 1,
            "amount" => null,
            "discount_amount" => null,
            "discount_rate" => null,
            "discount_num" => null,
            "shipping" => null,
            "handling" => null,
            "tax" => null,
            "tax_rate" => null,
            "weight" => null
        ];
        
        # A private variable containing an array with data regarding the button of the form.
        private static $form_button = [
            "buy_now" => "x-click-but01.gif",
            "payments" => "x-click-but02.gif",
            "check_out" => "x-click-but03.gif",
            "donate" => "x-click-but04.gif",
            "transfer" => "x-click-but05.gif",
            "pay_now" => "x-click-but06.gif"
        ];
        
        # Some predefined private properties used later.
        private $dom = null;
        private $form = null;
        private $instance_settings = null;
        
        # A public variable used to cache the products.
        public $product_index = 1;
        
        /* ---------- Private Functions ---------- */
		
		# The function that inserts a new line before an element.
		private function break ($element, $is_parent = false) {
			# Check whether the 'is_parent' flag is true.
			if ($is_parent) {
				# Cache the element's first child.
				$child = $element -> childNodes -> item(0);
				
				# Check whether the element has any children.
				if ($child) {
					# Create a text node (tab) and append it to the parent.
					$element -> insertBefore($this -> dom -> createTextNode("\n"), $child);
				}
				else {
					# Create a text node (tab) and append it to the parent.
					$element -> appendChild($this -> dom -> createTextNode("\n"));
				}
			}
			else {
				# Cache the parent of the element.
				$parent = $element -> parentNode;

				# Cache the next sibling of the element.
				$sibling = $element -> nextSibling;

				# Check whether the element has a next sibling.
				if ($sibling) {
					# Create a text node (tab) and insert it before the sibling.
					$parent -> insertBefore($this -> dom -> createTextNode("\n"), $sibling);
				}
				else {
					# Create a text node (tab) and append it to the parent.
					$parent -> appendChild($this -> dom -> createTextNode("\n"));
				}
			}
			
			# Return the context to allow chaining.
			return $this;
		}
		
		# The function that removes any and all tab and newline characters from the document.
		private function cleanse () {
			# Create a new XPath from the document.
			$xpath = new DOMXPath($this -> dom);
			
			# Cache all text nodes.
			$textnodes = $xpath -> query("//text()");
			
			# Iterate over every text node.
			foreach ($textnodes as $node) {
				# Check whether the value of the node is a whitespace character.
				if (preg_match("/^[\s]+$/", $node -> nodeValue)) {
					# FInd the node's parent in order to use it to delete the node.
					$node -> parentNode -> removeChild($node);
				}
			}
			
			# Return the context to allow chaining.
			return $this;
		}
		
		# The function that formats the document adding tab and newline characters where appropriate.
		private function format () {			
			# The function the does the formatting (aimed to be used recursively).
			$format_node = function ($given_node, $level) use (&$format_node) {
				# Cache all nodes.
				$nodes = $given_node -> childNodes;
				
				# Iterate over every node.
				foreach ($nodes as $node) {
					# Check whether the node is an element.
					if ($node -> nodeType == XML_ELEMENT_NODE) {
						# Cache the nodes's first child.
						$child = $node -> childNodes -> item(0);
						
						# Indent the element based on the level of depth.
						$this -> indent($node, $level);

						# Check whether the element has any children and if the first child is an element.
						if ($child && $child -> nodeType == XML_ELEMENT_NODE) {
							# Insert a newline inside the element.
							$this -> break($node, true);
							
							# Format the node.
							$format_node($node, $level + 1);
							
							# Indent the element's ending tag based on the level of depth.
							$this -> indent($node, $level, true);
						}
						
						# Insert a newline after the element.
						$this -> break($node);
					}
				}
			};
			
			# Format the document passing as the level of depth.
			$format_node($this -> dom, 0);
			
			# Return the context to allow chaining.
			return $this;
		}
		
		# The function that indents an element.
		private function indent ($element, $number = 1, $is_parent = false) {
			# Check if the 'is_parent' flag is true.
			if ($is_parent) {
				# Loop as many times as the given number.
				for ($index = 0; $index < $number; $index++) {
					# Create a text node (tab) and append it as child to the element.
					$element -> appendChild($this -> dom -> createTextNode("\t"));
				}
			}
			else {
				# Cache the parent of the element.
				$parent = $element -> parentNode;

				# Loop as many times as the given number.
				for ($index = 0; $index < $number; $index++) {
					# Create a text node (tab) and insert it before the element.
					$parent -> insertBefore($this -> dom -> createTextNode("\t"), $element);
				}
			}
			
			# Return the context to allow chaining.
			return $this;
		}
        
        # The function that gets raw POST data directly from the input stream.
		private function get_post_data () {
			# Create an array wherein the final data will be stored.
			$data = [];
			
			# Get the raw post data from the stream.
			$raw_data = file_get_contents("php://input");
			
			# Explode the data at the ampersands to turn it into an array.
			$raw_array = explode("&", $raw_data);
			
			# Iterate over every element of the array.
			foreach ($raw_array as $value) {
				# Explode the value at the equal signs to turn it into an array.
				$value = explode ("=", $value);
				
				# Check whether the value array has two elements and, if so, assign the url-decoded value to the key.
				if (count($value) == 2) $data[$value[0]] = urldecode($value[1]);
			}
			
			# Return the data array.
			return $data;
		}
        
        # The function that sends curl requests.
		private function curl ($data) {
			# Check whether curl is available.
			if (function_exists("curl_version")) {
				# Check whether the data passed is an array.
				if (is_array($data)) {
					# Check whether a url has been given.
					if (array_key_exists("url", $data)) {
						# Initiate a curl request.
						$curl = curl_init($data["url"]);

						# Set the necessary options.
						if (array_key_exists("http_version", $data)) curl_setopt($curl, CURLOPT_HTTP_VERSION, $data["http_version"]);
						if (array_key_exists("forbid_reuse", $data)) curl_setopt($curl, CURLOPT_FORBID_REUSE, $data["forbid_reuse"]);
						if (array_key_exists("request_type", $data)) curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $data["request_type"]);
						if (array_key_exists("connect_timeout", $data)) curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, +$data["connect_timeout"]);
						if (array_key_exists("timeout", $data)) curl_setopt($curl, CURLOPT_TIMEOUT, +$data["timeout"]);
						if (array_key_exists("return_transfer", $data)) curl_setopt($curl, CURLOPT_RETURNTRANSFER, +$data["return_transfer"]);
						if (array_key_exists("follow_location", $data)) curl_setopt($curl, CURLOPT_FOLLOWLOCATION, $data["follow_location"]);
						if (array_key_exists("ssl_version", $data)) curl_setopt($curl, CURLOPT_SSLVERSION, +$data["ssl_version"]);
						if (array_key_exists("ssl_verify_peer", $data)) curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, +$data["ssl_verify_peer"]);
						if (array_key_exists("ssl_verify_host", $data)) curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, +$data["ssl_verify_host"]);
						if (array_key_exists("post", $data)) curl_setopt($curl, CURLOPT_POST, $data["post"]);
						if (array_key_exists("post_fields", $data)) curl_setopt($curl, CURLOPT_POSTFIELDS, $data["post_fields"]);
						if (array_key_exists("http_header", $data)) curl_setopt($curl, CURLOPT_HTTPHEADER, $data["http_header"]);
						if (array_key_exists("ca_info", $data)) curl_setopt($curl, CURLOPT_CAINFO, $data["ca_info"]);

						# Execute the curl request and save the result.
						$this -> curl_result = curl_exec($curl);
						
						# Cache the error and its number provided there is one.
                        $this -> curl_error = curl_error($curl);
                        $this -> curl_errno = curl_errno($curl);
                        
                        # Cache the information of the curl request.
                        $this -> curl_info = json_decode(json_encode(curl_getinfo($curl)));

						# Terminate the curl request and exit the script.
						curl_close($curl);

						# Return the context.
						return $this;
					}
					else throw new Exception(__METHOD__ . " → The url of the request's target is required.");
				}
				else throw new Exception(__METHOD__ . " → An associative array is required but '" . ucfirst(gettype($data)) . "' was given instead.");
			}
			else throw new Exception(__METHOD__ . " → Curl is unavailable.");
		}
        
        # The function that creates an HTML input/select element and returns it.
        private function create_input ($tagname, $name = null, $value = null) {
            # Create the element specified.
            $element = $this -> dom -> createElement($tagname);

            # Set the 'type' attribute of the element.
            $element -> setAttribute("type", "hidden");
            
            # Check whether the name argument is not null.
            if (!is_null($name)) {
                # Set the 'name' attribute of the element.
                $element -> setAttribute("name", $name);
            }
            
            # Check whether the value argument is not null.
            if (!is_null($value)) {
                # Set the 'value' attribute of the element.
                $element -> setAttribute("value", $value);
            }
            
            # Return the created element.
            return $element;
        }
        
        /* ---------- Public Functions ---------- */
        
        # The constructor function of the class.
		public function __construct () {
			# Create a unique id for the PayPalInterface instance.
			$this -> id = ++self::$counter;
            
            # Create a new DOMDocument instance and cache it in the predefined private variable.
            $this -> dom = new DOMDocument;
		}
        
        # The function that creates a PayPal form.
        public function create_form ($data) {
            # Check whether the given argument is an array.
            if (is_array($data)) {
                # Normalise the value of the sandbox mode variable.
                $sandbox_mode = isset($this -> instance_settings) ? $this -> instance_settings["sandbox"] : self::$default_settings["sandbox"];
                
                # Create the form element, cache it and insert it into the document.
                $form = $this -> form = $this -> dom -> createElement("form");
                $this -> dom -> appendChild($form);
                
                # Set the 'class', 'action' and 'method' attributes of the form.
                $form -> setAttribute("class", "ip-paypal-form");
                $form -> setAttribute("action", "https://www" . ($sandbox_mode ? ".sandbox" : "") . ".paypal.com/cgi-bin/webscr");
                $form -> setAttribute("method", "post");
                
                # Iterate over every property-value pair of the data.
                foreach ($data as $name => $value) {
                    # Create an input and put it in the form.
                    $input = $this -> dom -> createElement("input");
                    $form -> appendChild($input);

                    # Set the 'type', 'name' and 'value' attributes of the input.
                    $input -> setAttribute("type", "hidden");
                    $input -> setAttribute("name", $name);
                    $input -> setAttribute("value", $value);
                }
                
                # Return the context.
                return $this;
            }
            else {
                # Throw an exception.
                throw new Exception(__METHOD__ . " → The given argument must be an assciative array. " . ucfirst(gettype($data)) . " was given instead.");
            }
        }
        
        # The function that creates a PayPal form button.
        public function create_form_button ($type) {
            # Check whether the given argument is a string.
            if (is_string($type)) {
                # Create a submit button and add it to the form.
                $submit = $this -> dom -> createElement("input");
                $this -> form -> appendChild($submit);
                
                # Set the 'class', 'type', 'src', 'name' and 'alt' attributes of the submit button.
                $submit -> setAttribute("class", "pi-paypal-button");
                $submit -> setAttribute("type", "image");
                $submit -> setAttribute("src", "https://images.paypal.com/images/" . self::$form_button[$type]);
                $submit -> setAttribute("name", "submit");
                $submit -> setAttribute("alt", "Pay Now with PayPal");
                
                # Return the context.
                return $this;
            }
            else {
                # Throw an exception.
                throw new Exception(__METHOD__ . " → The given argument must be a string. " . ucfirst(gettype($type)) . " was given instead.");
            }
        }
        
        # The function that creates a PayPal customer and adds it to the form.
        public function create_customer ($data) {
            # Check whether the given argument is an array.
            if (is_array($data)) {
                # Create a div element and put it in the form.
                $div = $this -> dom -> createElement("div");
                $this -> form -> appendChild($div);
                
                # Set the 'class' attribute of the div.
                $div -> setAttribute("class", "pi-paypal-customer");
                
                # Iterate over every property-value pair of the data.
                foreach ($data as $name => $value) {
                    # Create an input and put it in the div.
                    $input = $this -> dom -> createElement("input");
                    $div -> appendChild($input);

                    # Set the 'type', 'name' and 'value' attributes of the input.
                    $input -> setAttribute("type", "hidden");
                    $input -> setAttribute("name", $name);
                    $input -> setAttribute("value", $value);
                }
                
                # Return the context.
                return $this;
            }
            else {
                # Throw an exception.
                throw new Exception(__METHOD__ . " → The given argument must be an assciative array. " . ucfirst(gettype($data)) . " was given instead.");
            }
        }
        
        # The function that creates a PayPal product and adds it to the form.
        public function create_product ($data) {
            # Check whether the given argument is an array.
            if (is_array($data)) {
                # Cache the product index.
                $number = $this -> product_index++;
                
                # Create a div element and put it in the form.
                $div = $this -> dom -> createElement("div");
                $this -> form -> appendChild($div);
                
                # Set the 'class' attribute of the div.
                $div -> setAttribute("class", "pi-paypal-product");
                
                # The function that creates inputs for the products numbered properties.
                $create_numbered_inputs = function ($property, $value) use ($number, &$div) {
                    # Check whether the value is an array.
                    if (is_array($value)) {
                        # Iterate over the elements of the array.
                        for ($i = 0; $i < count($value); $i++) {
                            # Create an input and put it in the div.
                            $input = $this -> create_input("input", $property . ($i ? $i + 1 : "") . "_$number", $value[$i]);
                            $div -> appendChild($input);
                        }
                    }
                    else {
                        # Create an input and put it in the div.
                        $input = $this -> create_input("input", $property . "_$number", $value);
                        $div -> appendChild($input);
                    }
                };
                
                # Iterate over every property in the product numbered properties.
                foreach (self::$form_prod_num_props as $property => $default) {
                    # Check whether the iterated property is set in the data.
                    if (isset($data[$property])) {
                        # Create inputs for the numbered properties of the product.
                        $create_numbered_inputs($property, $data[$property]);
                    }
                    
                    # Check whether the default values of the property in the numbered properties is not null.
                    elseif (!is_null($default)) {
                        # Create inputs for the numbered properties of the product.
                        $create_numbered_inputs($property, $default);
                    }
                }
                
                # Check whether the 'options' property is set.
                if (isset($data["options"])) {
                    # Check whether the 'options' property is given an array value.
                    if (is_array($data["options"])) {
                        # Check whether there are less than or equal to the maximum of 7 lists.
                        if (count($data["options"]) <= 7) {
                            # Create an index to be used inside the loop and a priced index.
                            $index = 0;
                            $priced_index = null;

                            # Iterate over every key-value pair of the array.
                            foreach ($data["options"] as $option => $values) {
                                # Check whether there are a maximum of 10 options iin the list.
                                if (count($values) <= 10) {
                                    # Create an input element and put it into the div.
                                    $input = $this -> create_input("input", "on$index", $option);
                                    $div -> appendChild($input);

                                    # Create a select element and put it into the div.
                                    $select = $this -> create_input("select", "os$index");
                                    $div -> appendChild($select);
                                    
                                    # Check whether the value is an associative array.
                                    if (!isset($values[0])) {
                                        # Check whether a priced index has already been found.
                                        if (!is_null($priced_index)) {
                                            # Throw an exception and exit.
                                            throw new Exception(__METHOD__ . " → Only one list of options can be priced.");
                                            exit;
                                        }

                                        # Set the current index as the priced index.
                                        $priced_index = $index;
                                        
                                        # Create a second index for the follwoing loop.
                                        $jindex = 0;

                                        # Iterate over every key value pair of the values array.
                                        foreach ($values as $value => $price) {
                                            # Create an option element and put it into the select.
                                            $option = $this -> create_input("option", null, $value);
                                            $select -> appendChild($option);
                                            
                                            # Set the value as the text content of the option.
                                            $option -> textContent = $value;

                                            # Create two inputs and append them to the div.
                                            $div -> appendChild($this -> create_input("input", "option_select$jindex", $value));
                                            $div -> appendChild($this -> create_input("input", "option_amount$jindex", $price));
                                            
                                            # Increment the second index.
                                            $jindex++;
                                        }
                                    }
                                    else {
                                        # Iterate over every key value pair of the values array.
                                        foreach ($values as $value) {
                                            # Create an option element and put it into the select.
                                            $option = $this -> create_input("option", null, $value);
                                            $select -> appendChild($option);
                                            
                                            # Set the value as the text content of the option.
                                            $option -> textContent = $value;
                                        }
                                    }

                                    # Increment the index.
                                    $index++;
                                }
                                else {
                                    # Throw an exception.
                                    throw new Exception(__METHOD__ . " → There can be a maximum of 10 different options per list.");
                                }
                            }

                            # Check whether the priced index is greater than 0.
                            if ($priced_index > 0) {
                                # Create an input and put it into the div.
                                $div -> appendChild($this -> create_input("input", "option_index", $priced_index));
                            }
                        }
                        else {
                            # Throw an exception.
                            throw new Exception(__METHOD__ . " → There can be a maximum of 7 different options lists.");
                        }
                    }
                    else {
                        # Throw an exception.
                        throw new Exception(__METHOD__ . " → The 'options' property must be an associative array. " . ucfirst(gettype($data["options"])) . " was given instead.");
                    }
                }
                
                # Return the context.
                return $this;
            }
            else {
                # Throw an exception.
                throw new Exception(__METHOD__ . " → The given argument must be an assciative array. " . ucfirst(gettype($data)) . " was given instead.");
            }
        }
		
		# The function that gets/sets settings from/to the instance or the class.
		public function settings ($data = null) {
            # Check whether any arguments hasve been given.
            if (func_num_args()) {
                # Check whether the function was called by the context.
                if (isset($this) && $this instanceof self) {
                    # Check whether the settings of the context have been set before.
                    if (isset($this -> instance_settings)) {
                        # Iterate over every property of the settings.
                        foreach ($this -> instance_settings as $key => $value) {
                            # Set the value to the existent setting if it's not set.
                            $data[$key] = isset($data[$key]) ? $data[$key] : $this -> instance_settings[$key];
                        }
                    }
                    else {
                        # Iterate over every property of the default settings.
                        foreach (self::$default_settings as $key => $value) {
                            # Set the value to the existent default setting if it's not set.
                            $data[$key] = isset($data[$key]) ? $data[$key] : self::$default_settings[$key];
                        }
                    }
                    
                    # Set the data array to the 'instance_settings' property of the context.
                    $this -> instance_settings = $data;
                    
                    # Use the default settings to sort the data array's key in the proper order.
                    $data = array_merge(self::$default_settings, $data);

                    # Return the context.
                    return $this;
                }
                else {
                    # Iterate over every property of the default settings.
                    foreach (self::$default_settings as $key => $value) {
                        # Set the value to the existent default setting if it's not set.
                        $data[$key] = isset($data[$key]) ? $data[$key] : self::$default_settings[$key];
                    }

                    # Use the default settings to sort the data array's key in the proper order.
                    $data = array_merge(self::$default_settings, $data);
                    
                    # Set the data array to the 'default_settings' property of the class.
                    self::$default_settings = $data;
                }
            }
			else {
                # Check whether the function was called by the context.
                if (isset($this) && $this instanceof self) {
                    # Return the settings, if set, or the default settings.
                    return isset($this -> instance_settings) ? $this -> instance_settings : self::$default_settings;
                }
                else {
                    # Return the default settings.
                    return self::$default_settings;
                }
            }
		}
        
        # The function that logs given data to the log file.
        public function log ($content, $overwrite = false) {
            # Normalise the value of the path of the log file.
            $path = isset($this -> instance_settings["log_path"]) ? $this -> instance_settings["log_path"] : self::$default_settings["log_path"];
            
            # Check whether a log file at the path doesn't exist.
            if (!is_file($path) && preg_match("/[\/]+/", $path)) {
                # Explode the path at the slashes to get each part.
                $parts = explode("/", $path);

                # Pop the last element from the parts and cache it as the file.
                $file = array_pop($parts);

                # Create a variable to store the created directory path.
                $dir = "";

                # Iterate over every part.
                foreach ($parts as $part) {
                    # Check whether the directory doesn't exist.
                    if (!is_dir($dir .= "$part/")) {
                        # Create the directory.
                        mkdir($dir);
                    }
                }

                # Update the path with the result.
                $path = "$dir$file";
            }
            
            # The variable the contains the main arguments of the 'file_put_contents' function.
            $arguments = [$path, $content . "\r\n"];
            
            # Check the 'overwrite' flag is false.
            if (!$overwrite) {
                # Add the FILE_APPEND constant to the arguments.
                $arguments[] = FILE_APPEND;
            }
            
            # Use the arguments to log the desired content to the log file and cache the result.
            $this -> log_result = !!file_put_contents(...$arguments);
            
            # Return the context.
            return $this;
        }
        
        # The function that handles the Instant Payment Notification of PayPal.
        public function ipn () {
            # Normalise the value of the sandbox mode variable.
            $sandbox_mode = isset($this -> sandbox_mode) ? $this -> sandbox_mode : self::$default_sandbox_mode;
            
            # Get the raw post data from the stream.
            $POST = $this -> get_post_data();
            
            # Convert the raw data array into and object and cache it.
            $this -> ipn_data = json_decode(json_encode($POST));

            # Cache the value that must be prepended to the response.
            $response = "cmd=_notify-validate";

            # Check whether the the 'get_magic_quotes_gpc' function exists and, if so, set a flag to true.
            if (function_exists("get_magic_quotes_gpc")) $get_magic_quotes_exists = true;

            # Iterate over every element of the post data.
            foreach ($POST as $key => $value) {
                # Check whether the flag is true and the function's return value is 1 and, if so, strip slashes and url-encode.
                if ($get_magic_quotes_exists && get_magic_quotes_gpc() == 1) $value = urlencode(stripslashes($value));

                # Otherwise, just url-encode.
                else $value = urlencode($value);

                # Append the key and value in a querystring form to the response variable.
                $response .= "&$key=$value";
            }
            
            # Initiate and send a curl request posting the IPN data back to PayPal to validate.
            $this -> curl([
                "url" => "https://ipnpb" . ($sandbox_mode ? ".sandbox" : "") . ".paypal.com/cgi-bin/webscr",
                "http_version" => CURL_HTTP_VERSION_1_1,
                "forbid_reuse" => 1,
                "connect_timeout" => 30,
                "return_transfer" => 1,
                "ssl_version" => 6,
                "ssl_verify_peer" => 1,
                "ssl_verify_host" => 2,
                "post" => 1,
                "post_fields" => $response,
                "http_header" => ["Connection: Close"],
                "ca_info" => __DIR__ . "/cacert.pem"
            ]);

            # Check whether the curl request failed.
            if (!$this -> curl_result) {
                # Throw a new exception.
                throw new Exception(__METHOD__ . " → cURL error: [{$this -> curl_errno}] {$this -> curl_error}");
            }
            
            # Cache the ipn result based on the HTTP code of the curl request.
            $this -> ipn_result = ($this -> curl_info -> http_code == 200);
            
            # Cache whether the ipn was verified by paypal.
            $this -> ipn_verified = (strcmp($result, "VERIFIED") == 0) ? true : ((strcmp($result, "INVALID") == 0) ? false : null);
            
            # Reply with an empty 200 response to indicate to paypal the IPN was received correctly.
            header("HTTP/1.1 200 OK");
            
            # Return the context.
            return $this;
        }
        
        # The function that prints the form of the instance.
        public function print_form () {
            # Check whether a form has been created.
            if (isset($this -> form)) {
                #header("Content-Type: text/plain");
                
                # Cleanse the document of any whitespace characters except single space.
                $this -> cleanse();

                # Format the document.
                $this -> format();
                
                # Save the form as HTML and print it.
                echo $this -> dom -> saveHTML($this -> form);
            }
            else {
                # Throw an exception.
                throw new Exception(__METHOD__ . " → There is no form to print. One must be created first.");
            }
        }
	}
?>
