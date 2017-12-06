<?php

$app->post('/list-modules/{module_type}', function(Request $request, Response $response) {
	//generic search function for modules. should be able to account for module score
	//TODO: location based results (frontend?)
	$module_type = $request->getArgument('module_type');
	$query = $this->get('db')->prepare('SELECT modules.mid, modules.title, modules.summary,modules.location, modules.score AS modules, module_types.mtid, module_types.title AS module_types FROM modules_to_types, filename FROM images, date_created, comment, score FROM comments INNER JOIN modules ON modules_to_types.mid = modules.mid INNER JOIN module_types ON modules_to_types.mtid = module_types.mtid LEFT OUTER JOIN images ON module.mid = images.mid LEFT OUTER JOIN comments ON module.mid = comments.mid WHERE module_types.mtid = :module_type OR modules_types.title = :module_type AND modules.active = TRUE ORDER BY score');
	try {
		$this->db->beginTransaction();
		$query->execute(array('module_type'=>$module_type));
		$result = $query->fetchAll();
		$this->db->commit();
	} catch (PDOException $error) {
		$this->db->rollback();
		$result = '{"Error":'.$error->getMessage().'}';
		$this->logger->addInfo("Error! ".$error->getMessage());
	}
	return $result; 
})

$app->get('/module/{module_id}', function(Request $request, Response $response) {
	$module_id = $request->getArgument('module_id');
	$query = $this->get('db')->prepare('SELECT title, summary,location, score, date_created FROM modules, msid, title, summary, order, participants, active FROM module_steps, date_created, comment, score FROM comments, filename FROM images, module_types.title AS module_types FROM modules_to_types INNER JOIN module_steps ON module_steps.mid = modules.mid LEFT OUTER JOIN comments ON comments.mid = modules.mid LEFT OUTER JOIN comments ON comments.msid = module_steps.msid LEFT OUTER JOIN images ON images.mid = modules.mid LEFT OUTER JOIN images ON images.msid = module_steps.msid INNER JOIN modules ON module_to_types.mid = modules.mid INNER JOIN module_types ON modules_to_types.mtid = module_types.mtid WHERE modules.mid = :module_id'); 
	try {
		$this->db->beginTransaction();
		$query->execute(array('module_id'=>$module_id));
		$result = $query->fetchAll();
		$this->db->commit();
	} catch (PDOException $error) {
		$this->db->rollback();
		$result = '{"Error":'.$error->getMessage().'}';
		$this->logger->addInfo("Error! ".$error->getMessage());
	}
	return $result;
});

$app->post('/create-module', function(Request $request, Response $response) {
	//create module with creator's email. anyone can do this. modules start with a score of 0, tags are added/assigned during module creation process. comments and tags have module ids - should modules contain an array of tag/comment ids to make things faster?
	//module images should get stored in their own table
	//module steps can have individual ratings and comments. add steps while creating the module. steps will have images, scores, and comments of their own so they should be their own table?
	$data = json_decode($request->getParsedBody(),true);
	$files =  $request->getUploadedFiles();
	$directory = $this->get('upload_directory');
	$module = array();
	$module['mid'] = false;
	$module['title'] = $data->title;
	$module['creator'] = $data->email;
	$module['score'] = 0;
	$module['active'] = false;
	$module['date_activated'] = null;
	$module['summary'] = $data->summary;
	$module['date_created'] = new Date('NOW');
	$module['location'] = $data->location;
	//generate random uuid
	$token = bin2hex(random_bytes(16));
	$module['uuid'] = $token;
	//we will iterate over steps in response and store in array down below
	$module['steps'] = array();
	$module['images'] = array();
	//loop through each step, scraping tags/images as we go
	$stepCount = 0;
	foreach ($data->steps as $s) {
		$step = array(
			'title' => $s->title,
			'summary' => $s->summary,
			'score' => 0,
			'active' => $s->active,
			'order' => $s->order,
			'msid' => false,
			'images' => array()
		);
		//loop through the images stored in the step which are individually keyed by step # (good luck front end omg)
		foreach ($files['step-'.$stepCount.'-images'] as $i) {
			 if ($i->getError() === UPLOAD_ERR_OK) {
	            $filename = moveUploadedFile($directory, $i);
				$step['images'][] = array('filename'=>$filename);
			}
		}
		$module['steps'][] = $step;
		$stepCount = $stepCount + 1;
	}
	//loop through main module images and save them
	foreach ($files['module_images'] as $mi) {
		if ($mi->getError() === UPLOAD_ERR_OK) {
            $filename = moveUploadedFile($directory, $mi);
            $module['images'][] = array('filename'=>$filename);
        }
	}

	//create module in db
	$query = $this->get('db')->preapre('INSERT INTO modules SET uuid= :uuid, score = :score, title = :title, creator = :creator, summary = :summary, location = :location, date_created = :date_created, active = :active, date_activated = :date_activated');

	try {
		$this->db->beginTransaction();
		$query->execute(array(
			'uuid'=>$module['uuid'],
			'title' => $module['title'],
			'score' => $module['score'],
			'creator' => $module['creator'],
			'summary' => $module['summary'],
			'location' => $module['location'],
			'date_created' => $module['date_created'],
			'active' => $module['active'],
			'date_activated' => $module['date_activated'],
		));
		$module['mid'] = $this->db->lastInsertId();
		$this->db->commit();
	} catch (PDOException $error) {
		$this->db->rollback();
		$this->logger->addInfo("Error Saving Module! ".$error->getMessage());
	}

	//now save the steps
	if ($module['mid']) {
		foreach ($module['steps'] as $step) {
			$query = $this->db->prepare('INSERT INTO module_steps SET mid = :mid, title = :title, summary = :summary, score = :score, participants = :participants, order = :order');
			try {
				$this->db->beginTransaction();
				$query->execute(array(
					'mid' => $module['mid'],
					'title' => $step['title'],
					'score' => $step['score'],
					'summary' => $step['summary'],
					'order' => $step['order'],
					'participants' => $step['participants']
				));
				$step['msid'] = $this->db->lastInsertId();
				$this->db->commit();
			} catch (PDOException $error) {
				$this->db->rollback();
				$this->logger->addInfo("Error Saving Step! ".$error->getMessage());
			}
			foreach ($steo['images'] as $image) {
				$query = $this->db->prepare('INSERT INTO images SET filename = :filename, msid = :msid');
				try {
					$this->db->beginTransaction();
					$query->execute(array(
						'filename' => $image['filename'],
						'msid' => $step['msid']
					));
					$this->db->commit();
				} catch (PDOException $error) {
					$this->db->rollback();
					$this->logger->addInfo("Error Saving Step! ".$error->getMessage());
				}				
			}	
		}
		//with new module + steps ids in hand, save images
		//module images first
		foreach ($module['images'] as $image) {
			$query = $this->db->prepare('INSERT INTO images SET filename = :filename, mid = :mid');
			try {
				$this->db->beginTransaction();
				$query->execute(array(
					'filename' => $image['filename'],
					'mid' => $module['mid']
				));
				$this->db->commit();
			} catch (PDOException $error) {
				$this->db->rollback();
				$this->logger->addInfo("Error Saving Image! ".$error->getMessage());
			}			
		}
	}
	//send email with master link
	$mail = new PHPMailer(true);
	try {
	    //Server settings
	    $mail->SMTPDebug = 2;                                 // Enable verbose debug output
	    $mail->isSMTP();                                      // Set mailer to use SMTP
	    $mail->Host = 'smtp1.theguide.com;smtp2.theguide.com';  // Specify main and backup SMTP servers
	    $mail->SMTPAuth = true;                               // Enable SMTP authentication
	    $mail->Username = 'feedback@theguide.com';                 // SMTP username
	    $mail->Password = 'secret';                           // SMTP password
	    $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
	    $mail->Port = 587;                                    // TCP port to connect to

	    //Recipients
	    $mail->setFrom('feedback@theguide.com', 'Mailer');
	    $mail->addAddress($module['creator'], 'Guide Contibutor'); 
	    $mail->addBCC('master@theguide.com');

	    //Content
	    $mail->isHTML(true);                                  // Set email format to HTML
	    $mail->Subject = 'DO NOT DELETE THIS EMAIL!!! Thank you for creating a new module on The Guide.';
	    $mail->Body    = '<h1>Thank You!</h1><p>Please confirm your module by clicking the link to Publish below.</p><p>Your valuable contribution has helped make the guide a more powerful platform. Please keep this email in your archives.</p><p>To edit or delete your module click one of the links below. No-one else has this email, but keep in mind anyone with access to this email basically controls your new module!</p><ul><li><a target="_blank" href="http://'.$_SERVER['HOST_NAME'].'/confirm/create-module/'.$module['mid'].'/'.$module['uuid'].'">Publish Module</a></li><li><a target="_blank" href="http://'.$_SERVER['HOST_NAME'].'/confirm/update-module/'.$module['mid'].'/'.$module['uuid'].'">Edit Module</a></li><li><a target="_blank" href="http://'.$_SERVER['HOST_NAME'].'/confirm/delete-module/'.$module['mid'].'/'.$module['uuid'].'">Delete Module</a></li></ul>';
	    $mail->AltBody = 'Thank you for publishing on the guide! Please copy and paste the following link into your browser to publish: &#xa; http://'.$_SERVER['HOST_NAME'].'/confirm/create-module/'.$module['mid'].'/'.$module['uuid'].'&#xa; To edit: http://'.$_SERVER['HOST_NAME'].'/confirm/update-module/'.$module['mid'].'/'.$module['uuid'].'&#xa To delete: http://'.$_SERVER['HOST_NAME'].'/confirm/delete-module/'.$module['mid'].'/'.$module['uuid'];

	    $mail->send();
	    $this->logger->addMessage('Message has been sent';
	} catch (Exception $e) {
	    $this->logger->addMessage('Mailer Error: ' . $mail->ErrorInfo);
	}
	$response->write('Module Saved');
});

$app->post('/update-module/{module_id}/{email_key}', function(Request $request, Response $response) {
	//edit modules. this should only be accessible via email link (think craigslist)
	//only way to edit steps (so saving should check if each step was edited and save those as well. can use a 'isedited' flag to make it easier)
	$mid = $request->getArgument('module_id') || false;
	$uuid = $request->getArgument('email_key') || false;
	//this is a dumb way to do this very important check. 
	//this should not continue if the uuid isn't the right thing
	if (!mid || !uuid) {
		$this->logger->addMessage('Bad Request, Missing IDs');
		$response->write('Bad Request, Missing IDs');
	} else {
		$data = json_decode($request->getParsedBody(),true);
		$files =  $request->getUploadedFiles();
		$directory = $this->get('upload_directory');
		//save module

		//update module in db
		$query = $this->get('db')->preapre('UPDATE modules SET title = :title, summary = :summary, location = :location WHERE mid = :mid AND uuid = :uuid');

		try {
			$this->db->beginTransaction();
			$query->execute(array(
				'mid' => $mid,
				'uuid' => $uuid,
				'title' => $data->title,
				'summary' => $data->summary,
				'location' => $data->location,
			));
			$this->db->commit();
		} catch (PDOException $error) {
			$this->db->rollback();
			$this->logger->addInfo("Error Updating Module! ".$error->getMessage());
		}
		//save any module iamges
		foreach ($files['module-images'] as $image) {
			$query = $this->db->prepare('INSERT INTO images SET filename = :filename, mid = :mid');
			try {
				$this->db->beginTransaction();
				$query->execute(array(
					'filename' => $image['filename'],
					'mid' => $mid
				));
				$this->db->commit();
			} catch (PDOException $error) {
				$this->db->rollback();
				$this->logger->addInfo("Error Saving Image! ".$error->getMessage());
			}			
		}
		//now save the steps
		foreach ($data->steps as $step) {
			//if the step exists, update it
			if ($step['msid']) {
				$query = $this->db->prepare('UPDATE module_steps SET title = :title, summary = :summary, participants = :participants, order = :order WHERE msid = :msid');
				try {
					$this->db->beginTransaction();
					$query->execute(array(
						'msid' => $step['msid'],
						'title' => $step['title'],
						'summary' => $step['summary'],
						'order' => $step['order'],
						'participants' => $step['participants']
					));
					$this->db->commit();
				} catch (PDOException $error) {
					$this->db->rollback();
					$this->logger->addInfo("Error Updating Step! ".$error->getMessage());
				}	
			} else {
				//create a new step
				$query = $this->db->prepare('INSERT INTO module_steps SET mid = :mid, title = :title, summary = :summary, score = :score, participants = :participants, order = :order');
				try {
					$this->db->beginTransaction();
					$query->execute(array(
						'mid' => $mid,
						'title' => $step['title'],
						'score' => 0,
						'summary' => $step['summary'],
						'order' => $step['order'],
						'participants' => $step['participants']
					));
					$step['msid'] = $this->db->lastInsertId();
					$this->db->commit();
				} catch (PDOException $error) {
					$this->db->rollback();
					$this->logger->addInfo("Error Saving Step! ".$error->getMessage());
				}
			}	
			//deal with any the images now that we have a step id for sure
			foreach ($files['step-'.$step['count'].'-images'] as $i) {
				 if ($i->getError() === UPLOAD_ERR_OK) {
		            $filename = moveUploadedFile($directory, $i);
					$query = $this->db->prepare('INSERT INTO images SET filename = :filename, msid = :msid');
					try {
						$this->db->beginTransaction();
						$query->execute(array(
							'filename' => $filename,
							'msid' => $step['msid']
						));
						$this->db->commit();
					} catch (PDOException $error) {
						$this->db->rollback();
						$this->logger->addInfo("Error Saving Image! ".$error->getMessage());
					}	
				}
			}
		}
		//delete any images tagged for removal
		foreach ($data->delete_images as $image) {
			//delete file
			unlink($image['filename']);
			$query->$this->db->prepare('DELETE * FROM images WHERE iid = :iid');
			try {
				$this->db->beginTransaction();
				$query->execute(array(
					'iid' => $image['iid']
				));
				$this->db->commit();
			} catch (PDOException $error) {
				$this->db->rollback();
				$this->logger->addInfo("Error Deleteing Image! ".$error->getMessage());
			}		
		}
		$response->write('Module Updated!');
	}
});


$app->post('/delete-module/{module_id}/{email_key}', function(Request $request, Response $response) {
	//delete module. only accessible via email link (craigslist)
	//modules are never truly deleted just made inactive
	$mid = $request->getArgument('module_id') || false;
	$uuid = $request->getArgument('email_key') || false;
	//still a really du,b way to make this check
	if (!$mid || !$uuid) {
		$query = $this->db->prepare('UPDATE modules SET active = FALSE WHERE mid = :mid AND uuid = :uuid');
		try {
			$this->db->beginTransaction();
			$query->execute(array(
				'uuid' => $uuid,
				'mid' => $mid
			));
			$this->db->commit();
			$response->write('Module Inactive. Use the publish link in your master email to make it active again.');
		} catch (PDOException $error) {
			$this->db->rollback();
			$this->logger->addInfo("Error Deactivating Module! ".$error->getMessage());
			$response->write('Error deactivating module, please try again');
		}		
	}
});

//TODO: figure out wtf is going on here
$app->group('/confirm', function(Request $request, Response $response) {
	//landing page for email confirmation, do database stuff
	$this->get('/create-module/{module_id}/{email_key}', function(Request $request, Response $response) {
		$mid = $request->getArgument('module_id') || false;
		$uuid = $request->getArgument('email_key') || false;
		//still a really du,b way to make this check
		if (!$mid || !$uuid) {
			$date_activated = new Date('NOW');
			$query = $this->db->prepare('UPDATE modules SET active = TRUE, date_activated = :date_activated WHERE mid = :mid AND uuid = :uuid');
			try {
				$this->db->beginTransaction();
				$query->execute(array(
					'uuid' => $uuid,
					'mid' => $mid,
					'date_activated' => $date_activated
				));
				$this->db->commit();
				$response->write('Module Active! Use the delete link in your master email to remove it from The Guide.');
			} catch (PDOException $error) {
				$this->db->rollback();
				$this->logger->addInfo("Error Activating Module! ".$error->getMessage());
				$response->write('Error activating module, please try again');
			}		
		}		
	});
	$this->get('/update-module/{module_id}/{email_key}', function(Request $request, Response $response) {
		//serve a page to edit the module
	});
	$this->get('/delete-module/{module_id}/{email_key}', function(Request $request, Response $response) {
		//serve a confirmation page to delete the module
	});
});