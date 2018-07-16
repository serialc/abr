
var ABR = {
    "options": { 
        "symbol_radius": 500,
        "map_zoom": 10
    },
    "data": {
        "markers": [],     
        "validation": { requests: 0, origins:[], destinations:[] }
    },
    "active": "submission",
    "just_arrived": true,
    "constants": {
        "file_size_limit": 1024 * 1024 * 10//MB
    }
};

ABR.onload = function() {
    var hlinks = ["submissionlink", "statuslink", "resultslink", "analyticslink", "documentationlink"],
        e, el,
        clear_active = function(idname) {
            for( e in hlinks ) {
                e = hlinks[e];
                el = document.getElementById(e);
                el.parentElement.classList.remove("active");
            }
        };

    // initialize GUI
    for( e in hlinks ) {
        e = hlinks[e];
        el = document.getElementById(e);
        if( e === "submissionlink") {
            el.addEventListener("click", function(event) {
                if( ABR.active !== "submission" ) {
                    clear_active(e);
                    ABR.show_submission_page();
                }
        }, false)};
        if( e === "statuslink") {
            el.addEventListener("click", function(event) {
                if( ABR.active !== "status" ) {
                    clear_active(e);
                    ABR.load_status_page();
                }
        }, false)};
        if( e === "resultslink") {
            el.addEventListener("click", function(event) {
                if( ABR.active !== "results" ) {
                    clear_active(e);
                    ABR.load_results_page();
                }
        }, false)}
        if( e === "analyticslink") {
            el.addEventListener("click", function(event) {
                if( ABR.active !== "analytics" ) {
                    clear_active(e);
                    ABR.load_analytics_page();
                }
        }, false)}
        if( e === "documentationlink") {
            el.addEventListener("click", function(event) {
                if( ABR.active !== "documentation" ) {
                    clear_active(e);
                    ABR.show_documentation_page();
                }
        }, false)}
    }

    // Setting up other events
    // Check the file to be uploaded
    document.getElementById("requests_text_file").addEventListener('change', function() {
        var w,
            file = this.files[0];

        if (file.size > ABR.constants.file_size_limit) {
            w = "The maximum upload size is currently " + Math.round(ABR.constants.file_size_limit/1024/1024) + "MB, your file is " + Math.round(file.size/1024/1024) + "MB.<br>";
        }

        if( file.type !== "text/plain" ) {
            w += "The file must be a text file. This file isn't it is of format " + file.type + ".<br>";
        }

        el = document.getElementById('upload_file_info');
        el.innerHTML = "<p>File <strong>" + file.name + "</strong> is <strong>" + Math.round(file.size/1024) + " KB</strong> and was last modified on <strong>" + new Date(file.lastModified) + "</strong>.</p>";

        // hide messages related to file processing
        document.getElementById('upload_errors').classList.add("hidden");
        document.getElementById('upload_messages').classList.add("hidden");
        document.getElementById('submit_messages').classList.add("hidden");
        document.getElementById('submit_errors').classList.add("hidden");
    });

    // upload the file to the server for validation and displaying on map for confirmation
    document.getElementById("submit_file").addEventListener('click', function() {
        var i, w;

        // clear values, errors etc...
        document.getElementById('upload_errors').classList.add("hidden");
        document.getElementById('submit_errors').classList.add("hidden");
        document.getElementById('upload_status').classList.add("hidden");
        document.getElementById('submit_messages').classList.add("hidden");
        document.getElementById('submit_file').disabled = true;
        document.getElementById('upload_messages').innerHTML = "Uploading file. <i class='fas fa-spinner fa-pulse'></i>";
        document.getElementById('upload_messages').classList.remove("hidden");

        // reset the requests, origin, destinations accumulation
        ABR.data.validation = { requests: 0, origins:[], destinations:[] };

        // make the request, submit the data
        $.ajax({
            // Your server script to process the upload
            url: 'php/upload_requests.php',
            type: 'POST',

            // Form data
            data: new FormData(document.getElementById("upload_requests_form")),
            // return data format is:
            dataType: 'json',

            // Tell jQuery not to process data or worry about content-type
            // You *must* include these options!
            cache: false,
            contentType: false,
            processData: false
        })
        .done(function(msg) {
            if( msg.outcome === 'uploaded' ) {
                // good, file is uploaded
                document.getElementById('upload_status').innerHTML = "Upload completed. Validation commencing...";
                document.getElementById('upload_status').classList.remove("hidden");
                // now we need to request the validation incrementally
                ABR.request_upload_validation( msg.filename, msg.lines, 0 );
            } else {
                // unexpected result
                document.getElementById('upload_errors').innerHTML = JSON.parse(msg);
                document.getElementById('upload_messages').classList.add("hidden");
            }
        })
        .fail(function(msg) {
            document.getElementById('upload_messages').classList.add("hidden");

            console.log(msg);
            if( msg.responseJSON instanceof Array ) {
                w = '';
                for( i in msg.responseJSON ) {
                    w += msg.responseJSON[i] + '<br>';
                }
                document.getElementById('upload_errors').innerHTML = w;
            } else {
                document.getElementById('upload_errors').innerHTML = msg.responseJSON;
            }
            document.getElementById('upload_errors').classList.remove("hidden");

            // enable the submit button
            document.getElementById('submit_file').disabled = false;
        })
        .always(function() {
        });
    });

    document.getElementById('submit_requests').addEventListener('click', ABR.submit_reviewed_requests);

    // Turn on/off processing
    document.getElementById('abr_turn_on_button').addEventListener('click', function() {
        $.ajax({
            url: "php/status.php",
            type: "POST",
            data: {"cmd": "turn_on"},
            cache: false
        })
        .done(function(msg) { ABR.update_processing_status(); })
        .fail(function(msg) { console.log(msg); });
    });
    document.getElementById('abr_turn_off_button').addEventListener('click', function() {
        $.ajax({
            url: "php/status.php",
            type: "POST",
            data: {"cmd": "turn_off"},
            cache: false
        })
        .done(function(msg) { ABR.update_processing_status(); })
        .fail(function(msg) { console.log(msg); });
    });

    // Clear the log file
    document.getElementById('abr_log_clear_button').addEventListener('click', function() {
        $.ajax({
            url: "php/status.php",
            type: "POST",
            data: {"cmd": "clear_log"},
            cache: false
        })
        .done(function(msg) { ABR.load_log_file(); })
        .fail(function(msg) { console.log(msg); });
    });

    // Refresh the log file
    document.getElementById('abr_log_refresh_button').addEventListener('click', function() { ABR.load_log_file(); });

    // Summary of requests refresh button will refresh the summary of requests
    document.getElementById('refresh_queue_summary_button').addEventListener('click', function() { ABR.update_queue_summary(); }); 

    // Results refresh button 
    document.getElementById('refresh_results_table_button').addEventListener('click', function() { ABR.load_results_table(); }); 

    // Results refresh button 
    document.getElementById('refresh_analytics_button').addEventListener('click', function() {
        ABR.update_requests_analytics_graph(4320);
    }); 

    // continually update the status of ABR every minute
    (function update_status () {
        if( ABR.active === "status" || ABR.just_arrived ) {
            ABR.update_processing_status(); 
            ABR.update_queue_summary();
            ABR.load_log_file();
            ABR.update_server_settings();
            ABR.load_disk_usage();
        }
        if( ABR.active === "results" || ABR.just_arrived ) {
            ABR.load_results_table();
        }
        ABR.just_arrived = false;

        // call again
        setTimeout( update_status, 60000);
    })(); // calls itself right away

};

ABR.load_results_table = function() {

    $.ajax({
        url: "php/data_summary.php?type=results",
        type: "GET",
        cache: false
    })
    .done(function(data) {
        html = "<table><thead><tr><th>Case study</th><th>Status</th><th class='text-center'>Success</th><th class='text-center'>Errors</th><th class='text-center'>Queued</th><th class='text-center'>Processed / Total</th><th>Zip / DL</th></tr></thead><tbody>";
        for( i in data ) {
            i = data[i];
            html += "<tr><td>" + i["case_study"] + "</td>" +
                "<td width=200><div title='Successfully retrieved' style='width:" + ((i["complete"]-i["errors"])/i["total"]*100) +
                "%' class='spark_complete'></div><div title='Errors' style='width:" + (i["errors"]/i["total"]*100) +
                "%' class='spark_errors'></div><div title='In queue' style='width:" + (i["queued"]/i["total"]*100) +
                "%' class='spark_queued'></div></td>" +
                "<td class='text-center'>" + (i["complete"] - i["errors"]) + "</td>" +
                "<td class='text-center'>" + i["errors"] + "</td>" +
                "<td class='text-center'>" + i["queued"] + "</td>" +
                "<td class='text-center'>" + i["complete"] + ' / ' + i["total"] + " (" + Math.floor(i["complete"] / i["total"] * 100) + "%)</td>" +
                "<td class=''>" +
                    "<a href='#' onclick='ABR.zip_case_study_results(event, \"" + i["case_study"] + "\")' title='Generate zip file' style='color: orange'><i class='fas fa-bolt'></i></a> " +
                    (i["zip_state"] ?  "<a href='php/download.php?instruction=download&case_study=" + i["case_study"] + "' title='" + i["case_study"] + " zip created on:&#013;" + i["zip_mod"] + "&#013;Size: " + (i["zip_size"]/1024/1024).toFixed(1) + " MB'><i class='fas fa-download'></i></a>" : "<a style='color: grey'><i class='fas fa-download'></i></a>") +
                "<a href='#' onclick='ABR.delete_case_study(event, \"" + i['case_study'] + "\")' title='Delete all data for case study " + i['case_study'] + "' style='color: red'><i class='fas fa-times-circle'></a>" +
                "</td>" +
                "</tr>";
        }
        html += '</tbody></table>';
        document.getElementById("results_table").innerHTML = "<p>" + html + "</p>";
    })
    .fail(function(msg) {
        // can't get results
        console.log(msg);
    });
};

ABR.zip_case_study_results = function(e, case_study) {
    // cancel the default action
    e.preventDefault();
    
    $.ajax({
        url: "php/download.php?instruction=zip&case_study=" + case_study,
        method: "GET",
        cache: false
    })
    .done(function(data) { console.log(data); })
    .fail(function(msg) { console.log(msg); });
};

ABR.load_log_file = function() {
    $.ajax({
        url: "log_files/abr_log_file.txt",
        cache: false
    })
    .done(function(data) { document.getElementById('abr_log_file_contents').innerHTML = "<p>" + data.replace(/\n/g, "<br>") + "</p>"; })
    .fail(function(msg) { console.log(msg); });
};

ABR.load_disk_usage = function() {
    var el = document.getElementById('abr_disk_usage'),
        el_alert = document.getElementById('abr_disk_usage_alert');

    el_alert.classList.remove('alert-success');
    el_alert.classList.remove('alert-warning');
    el_alert.classList.remove('alert-danger');

    $.ajax({
        url: "log_files/disk_usage.txt",
        cache: false
    })
    .done(function(data) {
        data = parseFloat(data, 10);
        el.innerHTML = data + " GB of 8 GB used";
        el_alert.classList.add('alert-' + (data < 4 ? 'success' : (data < 6 ? 'warning' : 'danger')));
    }).fail(function(msg) {
        console.log(msg);
        el.innerHTML = "Unknown usage";
    })
};

ABR.display_submitted_requests = function(data, display_map) {
    var sumstatel = document.getElementById('requests_summary'),
        summapel = document.getElementById('requests_map'),
        point, i;

    // show the section
    document.getElementById('requests_review_p2').classList.remove("hidden");

    sumstatel.innerHTML = "<p>Lines processed: <strong>" + data.lines + "</strong><br>" +
        "Requests submitted: <strong>" + ABR.data.validation.requests + "</strong></p>";

    if( display_map ) {
        document.getElementById('map_section').classList.remove("hidden");

        // Only create map if it doesn't already exist
        if( ABR.map ) {
            // refocus on new destination
            ABR.map.setView(ABR.data.validation.destinations[0], ABR.options.map_zoom);

            // remove previous markers from map
            for( i in ABR.data.markers ) {
                ABR.map.removeLayer(ABR.data.markers[i]);
            }
            // delete the data
            ABR.data.markers = [];
            
        } else {
            // create map
            ABR.map = L.map('requests_map').setView(ABR.data.validation.destinations[0], ABR.options.map_zoom);

            L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(ABR.map);
        }
    } else {
        document.getElementById('map_section').classList.add("hidden");
    }

    // Add each origin and destination to map
    for(point in ABR.data.validation.origins) {
        point = ABR.data.validation.origins[point];
        ABR.data.markers.push( L.circle(point, {radius: ABR.options.symbol_radius, color: '#ffaaaa'}).addTo(ABR.map) );
    }

    for(point in ABR.data.validation.destinations) {
        point = ABR.data.validation.destinations[point];
        ABR.data.markers.push( L.circle(point, {radius: ABR.options.symbol_radius, color: '#aaaaff'}).addTo(ABR.map) );
    }

    // Save the submitted request info for when the user submits it after review
    ABR.data.last_request_filename = data.filename;

    // enable the submission button
    document.getElementById('submit_requests').removeAttribute('disabled');
};

ABR.submit_reviewed_requests = function() {
    document.getElementById('submit_messages').classList.add("hidden");
    document.getElementById('submit_errors').classList.add("hidden");

    // ajax call adding file contents to DB
    $.ajax({
        url: "php/submit_reviewed_requests.php",
        method: "POST",
        data: {"filename": ABR.data.last_request_filename},
    })
    .done( function(msg) {
        console.log(msg);
        // disable the submission button and wipe ABR.data.last_request_filename
        document.getElementById('submit_requests').setAttribute('disabled', '');
        document.getElementById('submit_messages').innerHTML = msg.outcome;
        document.getElementById('submit_messages').classList.remove("hidden");
    })
    .fail( function(msg) {
        console.log(msg);
        document.getElementById('submit_errors').innerHTML = msg;
        document.getElementById('submit_errors').classList.remove("hidden");
    });
};

ABR.show_submission_page = function() {
    var el;

    el = document.getElementById(ABR.active + '_page');
    el.classList.add('hidden');
    ABR.active = "submission";
    el = document.getElementById(ABR.active + '_page');
    el.classList.remove('hidden');
};

ABR.update_server_settings = function() {

    // Retrieve the server settings
    $.ajax({
        url: "php/server_settings.php",
        cache: false
    })
    .done(function(data) {
        document.getElementById('server_settings_status').innerHTML = data;
    })
    .fail(function(msg) {
        console.log(msg);
    });
};

ABR.delete_case_study = function(e, case_study) {
    // cancel the default action
    e.preventDefault();

    $('.modal-title', '#main_modal').html(case_study);
    $('.modal-body', '#main_modal').html(
        "<p>Are you sure you wish to delete ALL the <strong>requests, data and zip files</strong> for this case study?</p>" +
        "<p>All requests with the case study name '<strong>" + case_study + "</strong>' will be deleted.</em></p>"
        );

    // After the modal appears, do the following...
    $('#main_modal').on('shown.bs.modal', function () {
        // nothing for now
    });

    // on submit handler
    $('.btn-danger', '#main_modal').html('Submit').click(function() {
        $.ajax({
            type: "POST",
            url: "php/delete_requests.php",
            data: {"case_study": case_study, "instruction": "delete_full"}
        })
        .done(function() {
            // hide the modal
            $('#main_modal').modal('toggle');
            // disable click handler that we just defined (and used)
            $('.btn-danger', '#main_modal').html('Submit').off('click');
        })
        .fail(function(e) {
            console.log(e);
        });
    });
    
    // Show the modal
    $('#main_modal').modal('toggle');
}
ABR.delete_pending_requests = function(case_study) {
    $('.modal-title', '#main_modal').html(case_study);
    $('.modal-body', '#main_modal').html(
        "<p>Are you sure you wish to delete ALL the queued requests for this case study?</p>" +
        "<p>All requests with the <u>same case study name</u> that have <strong>not been processed</strong> will be deleted.</em></p>"
        );

    // After the modal appears, do the following...
    $('#main_modal').on('shown.bs.modal', function () {
        // nothing for now
    });

    // on submit handler
    $('.btn-danger', '#main_modal').html('Submit').click(function() {
        $.ajax({
            type: "POST",
            url: "php/delete_requests.php",
            data: {"case_study": case_study, "instruction": "delete_queued"}
        })
        .done(function() {
            // hide the modal
            $('#main_modal').modal('toggle');
            // disable click handler that we just defined (and used)
            $('.btn-danger', '#main_modal').html('Submit').off('click');
        })
        .fail(function(e) {
            console.log(e);
        });
    });
    
    // Show the modal
    $('#main_modal').modal('toggle');
};

ABR.update_queue_summary = function() {
    var html,
        i;

    $.ajax({
        url: "php/data_summary.php?type=queue",
        type: "GET",
        cache: false
    })
    .done(function(data) {
        html = "<table><thead><tr><th>Case study</th><th class='text-center'>Priority</th><th class='text-center'>Mode</th><th class='text-center'>Live</th><th class='text-center'>Pending requests</th><th class='text-center'>Delete</th></tr></thead><tbody>";
        for( i in data ) {
            i = data[i];
            html += "<tr><td class='text-center'>" + i['case_study'] + "</td><td class='text-center'>" + i['priority'] + "</td><td class='text-center'>" + i['mode'] + "</td>" +
                "<td class='text-center'>" + (i['live'] === '1' ? 'yes' : 'no') + "</td><td class='text-center'>" + i['pending'] + "</td>" +
                "<td class='text-center'><a href='#' onclick='ABR.delete_pending_requests(\"" + i['case_study'] + "\")' title='Delete pending requests for " + i['case_study'] + "' style='color: red'><i class='fas fa-times-circle'></a>" +
                "</td></tr>";
        }
        html += '</tbody></table>';
        document.getElementById("requests_queue_summary").innerHTML = "<p>" + html + "</p>";
    })
    .fail(function(msg) {
        // can't get summary
        console.log(msg);
    });
};

ABR.update_processing_status = function() {

    $.ajax({
        url: "php/status.php",
        type: "POST",
        data: {"cmd": "get_status"},
        cache: false
    })
    .done(function(msg) {
        if( msg.state === "1" || msg.state === "2") {
            // Running!
            document.getElementById("abr_status_unknown").classList.add('hidden');
            document.getElementById("abr_status_down").classList.add('hidden');
            document.getElementById("abr_turn_on_button").classList.add('hidden');
            document.getElementById("abr_status_up").classList.remove('hidden');
            document.getElementById("abr_turn_off_button").classList.remove('hidden');
        }
        if( msg.state === "0" ) {
            // Not running.
            document.getElementById("abr_status_unknown").classList.add('hidden');
            document.getElementById("abr_status_up").classList.add('hidden');
            document.getElementById("abr_turn_off_button").classList.add('hidden');
            document.getElementById("abr_status_down").classList.remove('hidden');
            document.getElementById("abr_turn_on_button").classList.remove('hidden');
        }
    })
    .fail(function(msg) {
        // can't get status, larger problem exists do nothing
        console.log('Failed to retrieve status');
        console.log(msg);

        document.getElementById("abr_status_down").classList.add('hidden');
        document.getElementById("abr_turn_on_button").classList.add('hidden');
        document.getElementById("abr_status_up").classList.add('hidden');
        document.getElementById("abr_turn_off_button").classList.add('hidden');
        document.getElementById("abr_status_unknown").classList.remove('hidden');

        if( typeof msg.status !== 'undefined' && msg.status === 0 ) {
            document.getElementById("abr_status_unknown").innerHTML = "UNKNOWN: Are you connected to the internet?";
        } else {
            document.getElementById("abr_status_unknown").innerHTML = msg.responseJSON;
        }
    });
};

ABR.request_upload_validation = function( filename, total_lines, current_line ) {
    document.getElementById('upload_messages').classList.add("hidden");

    // make the request to validate some of the records
    $.ajax({
        url: 'php/upload_processing.php',
        type: 'POST',
        data: {"filename": filename, "total_lines": total_lines, "current_line": current_line },
        // return data format is:
        dataType: 'json',
        cache: false
    })
    .done(function(msg) {
        if( msg.outcome === 'progressing' ) {
            // append this batch to the total
            ABR.data.validation.requests += msg.requests;
            ABR.data.validation.origins = ABR.data.validation.origins.concat(msg.origins);
            ABR.data.validation.destinations = ABR.data.validation.destinations.concat(msg.destinations);

            document.getElementById('upload_status').innerHTML = "Validating:<br><span class='progress_bar' style='width: " + (msg.next_line/msg.total_lines*100) + "%;'></span></div>";
            // request the next batch be processed
            // wait a tiny bit to not hammer the server
            //setTimeout(function() {
            ABR.request_upload_validation( msg.filename, msg.total_lines, msg.next_line );
            //}, 200);
        }
        if( msg.outcome === 'complete' ) {
            // append this batch to the total
            ABR.data.validation.requests += msg.requests;
            ABR.data.validation.origins = ABR.data.validation.origins.concat(msg.origins);
            ABR.data.validation.destinations = ABR.data.validation.destinations.concat(msg.destinations);

            // show/hide HTML notification elements
            document.getElementById('upload_status').innerHTML = "Upload and validation completed.";

            // retrieve the origin, destinations and display them if there aren't too many
            if( msg.display_map ) {
                ABR.display_submitted_requests(msg, true);
            } else {
                ABR.display_submitted_requests(msg, false);
            }

            // enable the submission button
            document.getElementById('submit_file').disabled = false;
        }
    })
    .fail(function(msg) {
        document.getElementById('upload_errors').classList.remove("hidden");
        document.getElementById('upload_messages').classList.add("hidden");
        document.getElementById('upload_status').classList.add("hidden");

        // show the error(s)
        document.getElementById('upload_errors').innerHTML = msg.responseJSON.join('<br>');

        // enable the submission button
        document.getElementById('submit_file').disabled = false;
    })
    .always(function() {
        // nothing for now
    });

};

ABR.load_status_page = function() {
    var el;

    el = document.getElementById(ABR.active + '_page');
    el.classList.add('hidden');
    ABR.active = "status";
    el = document.getElementById(ABR.active + '_page');
    el.classList.remove('hidden');

    // DISABLED as it is now automatically loaded and refreshed
    // display ABR processing state and appropriate buttons
    //ABR.update_processing_status(); 
    // display summary of ABR queue
    //ABR.update_queue_summary();
    // display the log file
    //ABR.load_log_file();
};

ABR.load_results_page = function() {
    var el;

    el = document.getElementById(ABR.active + '_page');
    el.classList.add('hidden');
    ABR.active = "results";
    el = document.getElementById(ABR.active + '_page');
    el.classList.remove('hidden');
};

ABR.load_analytics_page = function() {
    var el;

    el = document.getElementById(ABR.active + '_page');
    el.classList.add('hidden');
    ABR.active = "analytics";
    el = document.getElementById(ABR.active + '_page');
    el.classList.remove('hidden');

    ABR.update_requests_analytics_graph(4320);
    ABR.update_disk_usage_analytics_graph(4320);
};

ABR.show_documentation_page = function() {
    var el;

    el = document.getElementById(ABR.active + '_page');
    el.classList.add('hidden');
    ABR.active = "documentation";
    el = document.getElementById(ABR.active + '_page');
    el.classList.remove('hidden');

};

ABR.update_disk_usage_analytics_graph = function( span_minutes ) {
    var line_data, mintime, maxtime,
        time_now = parseInt(Date.now() / 1000, 10); // epoch seconds

    // map config options
    var svg_par = {
        width: 1110,
        height: 250,
        bottom: 60,
        left: 60,
        top: 20,
        right: 20,
        text_size: 16};

    // remove previous figure if it exists
    d3.select("#disk_space_usage_analytics_figure_svg").remove();

    // get the historical requests data
    $.ajax({
        url: "log_files/disk_usage_log_file.txt",
        cache: false
    }).done(function(data) {

        // repackage the data into objects, time is now in days since 1970
        line_data = data.trim().split('\n').map(function(d) {
            p = d.split(',');
            if( span_minutes > 0 && parseInt(p[0], 10) < (time_now - span_minutes * 60) ) {
                return;
            }
            return {x: parseInt(p[0], 10)/60/60/24, y: p[1]}
        });
        // removes undefined values
        line_data = line_data.filter(function(n){ return n != undefined });

        // create the graph when the data is loaded
        var svgContainer = d3.select("#disk_space_usage_analytics_figure").append('svg')
            .attr("width", "100%").attr("height", "")
            .attr("id", "disk_space_usage_analytics_figure_svg")
            .attr("viewBox", "0 0 " + svg_par.width + " " + svg_par.height);

        svgContainer.append('rect').attr("class", "bg").attr("width", "100%").attr("height", "100%");
        
        var xrange = line_data.map(function(d) {return d.x});
        var yrange = line_data.map(function(d) {return d.y});
        maxtime = time_now / 60 / 60 / 24;
        if( span_minutes === 0 ) {
            mintime = d3.min(xrange);
        } else {
            mintime = maxtime - (span_minutes / 60 / 24);
        }

        var xlinearScale = d3.scaleLinear()
            .domain([0, maxtime - mintime])
            .range([svg_par.left, svg_par.width - svg_par.right]);

        var ylinearScale = d3.scaleLinear()
            .domain([0, 8])
            .range([svg_par.height - svg_par.bottom, svg_par.top]);

        var lineFunction = d3.line()
            .x(function(d) { return xlinearScale(maxtime - d.x); })
            .y(function(d) { return ylinearScale(d.y); })

        var lineGraph = svgContainer.append("path")
            .attr("d", lineFunction(line_data))
            .attr("stroke", "blue")
            .attr("stroke-width", 1)
            .attr("fill", "none");

        // Y-axis
        var yaxis_ticks = d3.axisLeft(ylinearScale).ticks(5);
        svgContainer.append("g")
            .call(yaxis_ticks)
            .attr("transform", "translate(" + (svg_par.left - 5) + ", 0)");
        svgContainer.append("text")
            .attr("x", svg_par.width/2 - 40)
            .attr("y", svg_par.height - 10)
            .text("Days since present");

        // X-axis
        var xaxis_ticks = d3.axisBottom(xlinearScale).ticks(5);
        svgContainer.append("g")
            .call(xaxis_ticks)
            .attr("transform", "translate(0, " + (svg_par.height - svg_par.bottom + 5) + ")");
        svgContainer.append("text")
            .attr("x", -svg_par.height/2 - 55)
            .attr("y", 20)
            .attr("transform", "rotate(-90)") 
            .text("Disk usage (GB)");

    }).fail(function(msg) { console.log(msg); });
}
ABR.update_requests_analytics_graph = function( span_minutes ) {
    var line_data, mintime, maxtime,
        time_now = parseInt(Date.now() / 1000, 10); // epoch seconds

    // map config options
    var svg_par = {
        width: 1110,
        height: 250,
        bottom: 60,
        left: 60,
        top: 20,
        right: 20,
        text_size: 16};

    // remove previous figure if it exists
    d3.select("#requests_analytics_figure_svg").remove();

    // get the historical requests data
    $.ajax({
        url: "log_files/abr_requests_count_log_file.txt",
        cache: false
    }).done(function(data) {

        // repackage the data into objects, time is now in days since 1970
        line_data = data.trim().split('\n').map(function(d) {
            p = d.split(',');
            if( span_minutes > 0 && parseInt(p[0], 10) < (time_now - span_minutes * 60) ) {
                return;
            }
            return {x: parseInt(p[0], 10)/60/60/24, y: parseInt(p[1], 10)}
        });
        // removes undefined values
        line_data = line_data.filter(function(n){ return n != undefined });

        // create the graph when the data is loaded
        var svgContainer = d3.select("#requests_analytics_figure").append('svg')
            .attr("width", "100%").attr("height", "")
            .attr("id", "requests_analytics_figure_svg")
            .attr("viewBox", "0 0 " + svg_par.width + " " + svg_par.height);

        svgContainer.append('rect').attr("class", "bg").attr("width", "100%").attr("height", "100%");
        
        var xrange = line_data.map(function(d) {return d.x});
        var yrange = line_data.map(function(d) {return d.y});
        maxtime = time_now / 60 / 60 / 24;
        if( span_minutes === 0 ) {
            mintime = d3.min(xrange);
        } else {
            mintime = maxtime - (span_minutes / 60 / 24);
        }

        var xlinearScale = d3.scaleLinear()
            .domain([0, maxtime - mintime])
            .range([svg_par.left, svg_par.width - svg_par.right]);

        var ylinearScale = d3.scaleLinear()
            .domain([0, d3.max(yrange)])
            .range([svg_par.height - svg_par.bottom, svg_par.top]);

        var lineFunction = d3.line()
            .x(function(d) { return xlinearScale(maxtime - d.x); })
            .y(function(d) { return ylinearScale(d.y); })

        var lineGraph = svgContainer.append("path")
            .attr("d", lineFunction(line_data))
            .attr("stroke", "blue")
            .attr("stroke-width", 1)
            .attr("fill", "none");

        // Y-axis
        var yaxis_ticks = d3.axisLeft(ylinearScale).ticks(5);
        svgContainer.append("g")
            .call(yaxis_ticks)
            .attr("transform", "translate(" + (svg_par.left - 5) + ", 0)");
        svgContainer.append("text")
            .attr("x", svg_par.width/2 - 40)
            .attr("y", svg_par.height - 10)
            .text("Days since present");

        // X-axis
        var xaxis_ticks = d3.axisBottom(xlinearScale).ticks(5);
        svgContainer.append("g")
            .call(xaxis_ticks)
            .attr("transform", "translate(0, " + (svg_par.height - svg_par.bottom + 5) + ")");
        svgContainer.append("text")
            .attr("x", -svg_par.height/2 - 55)
            .attr("y", 20)
            .attr("transform", "rotate(-90)") 
            .text("Requests per minute");

    }).fail(function(msg) { console.log(msg); });
};

ABR.onload();
