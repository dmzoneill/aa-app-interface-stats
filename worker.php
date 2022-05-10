<?php

class AppInterfaceStats {

    protected $raw_commits = [];
    protected $commit_diff_times = [];
    protected $commits_time_by_year = [];
    protected $commits_time_by_month = [];
    protected $commits_time_by_day = []; 
    protected $overall_stats = [];

    public function __construct() {
        if(file_exists("data.json")) {
            $this->raw_commits = json_decode(file_get_contents("data.json"));
        }
        else {
            $this->get_commits();
        }
        $this->build_tree();
        $this->calculate_year_diff();
        $this->calculate_month_diff();
        $this->calculate_day_diff();
        file_put_contents("consume.json", json_encode($this->overall_stats));
    }

    private function get_commits() {
        $per_page = 30;
        $page = 1;
        $pages = 2000;

        while($page < $pages) {
            $ch = curl_init();
            $headers = array(
                "PRIVATE-TOKEN: " . getenv('GITLAB_TOKEN');
            );
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_URL, "https://gitlab.cee.redhat.com/api/v4/projects/13582/merge_requests?page=" . $page . "&per_page=" . $per_page);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $output = curl_exec($ch);
            curl_close($ch);    
            $json = json_decode($output);
            if(!is_array($json)) {
                break;
            }
            $this->raw_commits = array_merge($this->raw_commits, $json);
            $json_string = json_encode($this->raw_commits);
            file_put_contents("data.json", $json_string);

            $page++;
            sleep(0.5);
        }
    }

    private function build_tree()
    {
        print("Build Tree\n");
        foreach($this->raw_commits as $commit) {
            if($commit->merged_at == null) {
                continue; 
            }

            $created_at = strtotime($commit->created_at);
            $merged_at = strtotime($commit->merged_at);
            $year = date('Y', $merged_at);
            $month = date('F', $merged_at);
            $day = date('l', $merged_at);

            if(!isset($this->commit_diff_times[$year])) {
                // print("Create year $year\n");
                $this->commit_diff_times[$year] = [];
                // print_r($this->commit_diff_times);
            }

            if(!isset($this->commit_diff_times[$year][$month])) {
                // print("Create month $month\n");
                $this->commit_diff_times[$year][$month] = [];
                // print_r($this->commit_diff_times);
            }

            if(!isset($this->commit_diff_times[$year][$month][$day])) {
                // print("Create day $day\n");
                $this->commit_diff_times[$year][$month][$day] = [];
                // print_r($this->commit_diff_times);
            }

            $this->commit_diff_times[$year][$month][$day][] = $merged_at - $created_at;
        }
    }

    private function calculate_year_diff()
    {
        print("Calcuting year average\n");
        $years = [];

        foreach($this->commit_diff_times as $year_key => $year_arr) {
            if(!isset($years[$year_key])){
                $years[$year_key] = [];
            }

            foreach($this->commit_diff_times[$year_key] as $month_key => $month_arr) {
                foreach($this->commit_diff_times[$year_key][$month_key] as $day_key => $day_arr) {
                    $years[$year_key] = array_merge($years[$year_key], $day_arr);
                }
            }
        }

        $this->overall_stats['yearly'] = [];
        foreach($years as $year_key => $year_arr) {
            print($year_key . " = " );
            print(round(array_sum($years[$year_key]) / count($years[$year_key]) / 60 / 60,2));
            print(" hrs\n");
            $this->overall_stats['yearly'][$year_key] = round(array_sum($years[$year_key]) / count($years[$year_key]) / 60 / 60,2);            
        }
    }

    private function calculate_month_diff()
    {
        print("Calcuting month average\n");
        $months = [];

        foreach($this->commit_diff_times as $year_key => $year_arr) {
            foreach($this->commit_diff_times[$year_key] as $month_key => $month_arr) {
                $key = $year_key . "-" . $month_key;
                if(!isset($months[$key]))
                {
                    $months[$key] = [];
                }

                foreach($this->commit_diff_times[$year_key][$month_key] as $day_key => $day_arr) {
                    $months[$key] = array_merge($months[$key], $day_arr);
                }
            }
        }

        $this->overall_stats['monthly'] = [];
        foreach($months as $month_key => $month_arr) {
            print($month_key . " = " );
            print(round(array_sum($months[$month_key]) / count($months[$month_key]) / 60 / 60,2));
            print(" hrs\n");
            $this->overall_stats['monthly'][$month_key] = round(array_sum($months[$month_key]) / count($months[$month_key]) / 60 / 60,2);    
        }
    }

    private function calculate_day_diff()
    {
        print("Calcuting day average\n");
        $days = [];

        foreach($this->commit_diff_times as $year_key => $year_arr) {
            foreach($this->commit_diff_times[$year_key] as $month_key => $month_arr) {
                foreach($this->commit_diff_times[$year_key][$month_key] as $day_key => $day_arr) {
                    // $key = $year_key . "-" . $month_key . "-" . $day_key;
                    $key = $year_key . "-" . $day_key;
                    if(!isset($days[$key]))
                    {
                        $days[$key] = [];
                    }

                    $days[$key] = array_merge($days[$key], $day_arr);
                }
            }
        }

        //print_r($days);

        $this->overall_stats['daily'] = [];
        foreach($days as $day_key => $day_arr) {
            print($day_key . " = " );
            print(round(array_sum($days[$day_key]) / count($days[$day_key]) / 60 / 60,2));
            print(" hrs\n");
            $this->overall_stats['daily'][$day_key] = round(array_sum($days[$day_key]) / count($days[$day_key]) / 60 / 60,2);   
        }
    }
}

new AppInterfaceStats();
