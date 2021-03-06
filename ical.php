<?php
	require_once 'libs/iCalcreator.class.php';
	require_once 'network.php';
	require_once 'Course.Class.php';
	 
	function setCourseEvent(Course $course, vcalendar $ical, array $info) {
		$startTime = semInfo($info['year'], $info['sem']);
		$lessons = $course->lessons;
		
		//add lessons
		foreach ($lessons as $lesson) {
			$lessonEvent = & $ical->newComponent('vevent');
			
			//set summary(name)
			$lessonEvent->setProperty('summary', $lesson->summary);
			
			//set start and end time. 
			//start
			$start = fewDaysNextOrBefore($startTime, '+'.($lesson->time->wkDay - 1).' days');
			$shour = ($lesson->time->startTime)/100;
			$smin = ($lesson->time->startTime)%100;
			$start['hour'] = $shour;
			$start['min'] = $smin;
			$start['sec'] = 0;
			$lessonEvent->setProperty('dtstart', $start);
			//end
			$end = fewDaysNextOrBefore($startTime, '+'.($lesson->time->wkDay - 1).' days');
			$ehour = ($lesson->time->endTime)/100;
			$emin = intval($lesson->time->endTime)%100;
			$end['hour'] = $ehour;
			$end['min'] = $emin;
			$end['sec'] = 0;
			$lessonEvent->setProperty('dtend', $end);   
			       
			//set location
			$lessonEvent->setProperty('LOCATION', $lesson->venue);
			
			//set description
			$lessonEvent->setProperty('description', $lesson->description);
			
			//set week repeat
			if(!$lesson->wkRepeatValid)
				continue;
			$endTime = fewDaysNextOrBefore($startTime, '+14 weeks');
			$rule = array('FREQ' => 'WEEKLY'
				, 'UNTIL' => $endTime['year'].'/'.$endTime['month'].'/'.$endTime['day']);
			$lessonEvent->setProperty("rrule", $rule);
			$exdate = array('year' => $start['year']
						, 'month' => $start['month']
						, 'day' => $start['day']);
			$recess = fewDaysNextOrBefore($exdate, '+7 weeks');
			$recess['hour'] = $shour;
			$recess['min'] = $smin;
			$recess['sec'] = 0;
			$wk = $lesson->time->wkRepeat;
			$exdates = array();
			array_push($exdates, $recess);
			for($i=0;$i<13;$i++) {
				if($i<7)
					$j = $i;
				else
					$j = $i+1;
				if(!$wk[$i]) {
					$w = fewDaysNextOrBefore($exdate, '+'.$j.' weeks');
					$w['hour'] = $shour;
					$w['min'] = $smin;
					$w['sec'] = 0;
					array_push($exdates, $w);
				}
			}
			$lessonEvent->setProperty('exdate', $exdates, array('TZID'=>$info['tz']));
		}
		
		//add examtime
		$examtime = $course->examTime;
		if($examtime==null)
			return;
		$start = array('year' => $examtime->year
					, 'month' => $examtime->month
					, 'day' => $examtime->day
					, 'hour' => ($examtime->startTime)/100
					, 'min' => ($examtime->startTime)%100
					, 'sec' => 0);
		$end = array('year' => $examtime->year
		, 'month' => $examtime->month
					, 'day' => $examtime->day
					, 'hour' => ($examtime->endTime)/100
					, 'min' => ($examtime->endTime)%100
					, 'sec' => 0);
		$exam = & $ical->newComponent('vevent');
		$exam->setProperty('dtstart', $start);
		$exam->setProperty('dtend', $end);
		$exam->setProperty('summary', $course->code.' EXAM!');
		$exam->setProperty( 'description', $course->code.', '.$course->name.', '.$course->au);
		return;
	}//setCourseEvent(Course $course, vcalendar $ical, array $info);
	
	function createCalWithCustomInformation(array $info) {
		//check important value
		if(!array_key_exists('year', $info) || !array_key_exists('sem', $info) || !array_key_exists('courses', $info))
			return null;
		
		$mode = array_key_exists('mode', $info) ? $info['mode'] : 'auto';
		$unique_id = array_key_exists('unique_id', $info) ? $info['unique_id'] : rand();
		$TZID = array_key_exists('tz', $info) ? $info['tz'] : 'Asia/Singapore';
		$filename = array_key_exists('filename', $info) ? $info['filename'] : 'Course_Cal_file';
		$year = $info['year'];
		$sem = $info['sem'];
		$courses = $info['courses'];
		if(!is_numeric($year) || !is_numeric($sem))
			return null;
		
		//create ical
		$config = array('unique_id' => $unique_id
					, 'TZID' => $TZID
					, 'filename' => $filename);
		$ical = new vcalendar($config);
		
		//add courses
		foreach($courses as $course) {
			$c = null;
			if($mode=='manual') {
				$c = Course::getInstanceWithCourseInfo($course);
			}
			else {
				$course['year'] = $info['year'];
				$course['sem'] = $info['sem'];
				$c = Course::getInstanceAuto($course);
			}
			if($c)
				setCourseEvent($c, $ical, $info);
		}
		return array('ics' => $ical);
	}//createCalWithCustomInformation(array $info);
?>
