function login(clientId, username, password, dmat_num) {
    const logId = '#log_' + dmat_num;
    const btnId = '#login_' + dmat_num;

    $.ajax({
        url: 'meroshare_login.php?clientId=' + clientId + '&username=' + username + '&password=' + password + '&dmat_num=' + dmat_num,
        beforeSend: function() {
            // Disable button and add a sleek SVG spinner
            $(btnId).prop('disabled', true).addClass('opacity-70 cursor-not-allowed');
            $(btnId).prepend(`
                <svg id="spinner_${dmat_num}" class="animate-spin -ml-1 mr-2 h-3 w-3 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            `);
            
            // Initial log "Live" state
            $(logId).html(`
                <div class="flex items-center gap-2 text-[10px] text-blue-400 font-mono italic animate-pulse">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                    AUTHENTICATING...
                </div>
            `);
        },
        success: function(result) {
            $(btnId).prop('disabled', false).removeClass('opacity-70 cursor-not-allowed');
            $(`#spinner_${dmat_num}`).remove();

            if (IsJsonString(result) === true) {
                var json = JSON.parse(result);
                
                if (json.message == "Log in successful.") {
                    lastLogin(dmat_num);
                    // Success Style: Green badge with Check SVG
                    $(logId).html(`
                        <div class="flex items-center gap-2 text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2 py-1 rounded-lg animate-in zoom-in duration-300">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            <span class="text-[10px] font-bold uppercase tracking-tight">Success</span>
                        </div>
                    `);
                } else {
                    // Failure Style: Red badge
                    $(logId).html(`
                        <div class="flex items-center gap-2 text-red-400 bg-red-500/10 border border-red-500/20 px-2 py-1 rounded-lg">
                            <i class="fas fa-exclamation-circle text-[10px]"></i>
                            <span class="text-[10px]">${json.message}</span>
                        </div>
                    `);
                }
            } else {
                // Handling raw error strings
                let errorContent = (/{/.test(result)) ? result.split("{")[0] : result;
                $(logId).html(`
                    <div class="text-amber-500 text-[10px] font-mono border-l-2 border-amber-500 pl-2">
                        ERR: ${errorContent}
                    </div>
                `);
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            $(btnId).prop('disabled', false).removeClass('opacity-70 cursor-not-allowed');
            $(`#spinner_${dmat_num}`).remove();
            $(logId).html(`<span class="text-red-500 text-[10px] font-mono uppercase underline">Fatal Error: ${errorThrown}</span>`);
        }
    });
}
function IsJsonString(str) {
    try {
        JSON.parse(str);
    } catch (e) {
        return false;
    }
    return true;
}

function lastLogin(dmat_num) {
    const targetId = '#lastLogin_' + dmat_num;

    $.ajax({
        url: 'json-api.php?type=lastLogin&dmat_num=' + dmat_num,
        method: 'GET', // Explicitly set method
        success: function(rawResult) {
            // 1. Clean the result (remove whitespace/newlines)
            let result = rawResult.trim();
            
            // 2. Log to console so you can see exactly what the server sent
            console.log("Demat: " + dmat_num + " | Server Response: " + result);

            if (!result) {
                $(targetId).html('<span class="text-slate-600 text-[10px]">No Data</span>');
                return;
            }

            let displayResult = "";
            // Check for "expired" or if the time is too long (e.g., "70 min ago")
            let isExpired = result.toLowerCase().includes("expired") || parseInt(result) > 60;

            if (isExpired) {
                displayResult = `
                    <div class="flex items-center justify-center gap-2 text-red-400 bg-red-500/10 border border-red-500/20 px-3 py-1 rounded-full w-fit mx-auto">
                        <svg class="w-3 h-3 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span class="text-[10px] font-bold uppercase tracking-widest">Expired</span>
                    </div>`;
            } else {
                displayResult = `
                    <div class="flex items-center justify-center gap-2 text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-3 py-1 rounded-full w-fit mx-auto">
                        <span class="relative flex h-1.5 w-1.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500"></span>
                        </span>
                        <span class="text-[10px] font-bold uppercase tracking-widest">${result}</span>
                    </div>`;
            }

            // Apply the update with a crisp fade
            $(targetId).stop(true, true).hide().html(displayResult).fadeIn(300);
        },
        error: function(xhr, status, error) {
            console.error("LastLogin API Error: " + error);
            $(targetId).html('<span class="text-red-500 text-[9px] font-mono">OFFLINE</span>');
        }
    });
}

function loginall() {
    const btnId = '#loginallbtn';
    
    $.ajax({
        url: 'json-api.php?type=loginall',
        beforeSend: function() {
            // Disable button and show "Batch Processing" state
            $(btnId).prop('disabled', true).addClass('opacity-70 cursor-not-allowed');
            $(btnId).html(`
                <svg id="spinner_loginallbtn" class="animate-spin h-4 w-4 mr-2 inline-block text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Processing Batch Login...
            `);
        },
        success: function(result) {
            if (IsJsonString(result)) {
                const json = JSON.parse(result);
                
                // Use a staggered loop to create a "Live" waterfall effect
                json.forEach((user, index) => {
                    setTimeout(() => {
                        // Call our updated login function for each user
                        login(user.clientId, user.username, user.password, user.dmat_num);
                        
                        // If this is the last user, reset the main button
                        if (index === json.length - 1) {
                            setTimeout(() => {
                                $(btnId).prop('disabled', false)
                                    .removeClass('opacity-70 cursor-not-allowed')
                                    .html('<i class="fas fa-users-cog mr-2"></i> Login All Accounts');
                            }, 1000);
                        }
                    }, index * 500); // 500ms gap between each login trigger
                });
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            $(btnId).prop('disabled', false).removeClass('opacity-70').text('Batch Failed - Retry');
            console.error("Batch Login Error: " + errorThrown);
        }
    });
}


function generateButtons(dmat_num) {
    const btnContainer = '#btn_' + dmat_num;
    const logId = '#log_' + dmat_num;

    $.ajax({
        url: 'applicableissue.php?dmat_num=' + dmat_num,
        beforeSend: function() {
            // Show a "Scanning" state in the log
            $(logId).html(`
                <div class="flex items-center gap-2 text-[10px] text-indigo-400 font-mono animate-pulse">
                    <svg class="animate-spin h-3 w-3" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    FETCHING_ISSUES...
                </div>
            `);
        },
        success: function(result) {
            if (IsJsonString(result) === true) {
                const json = JSON.parse(result);
                let html = '<div class="flex flex-wrap gap-2 justify-center">';

                if (json.table.length > 0) {
    json.table.forEach((issue, index) => {
        // Determine style and ID based on button type
        const isReapply = issue.btnType === "reapplyIPO";
        const idPrefix = isReapply ? "reapplyipo" : "applyipo";
        
        // Use indigo for Apply, Rose (Red/Danger) for Reapply
        const themeClasses = isReapply 
            ? "bg-rose-600/10 hover:bg-rose-600 text-rose-400 hover:text-white border-rose-500/20 hover:border-rose-500 shadow-rose-900/10" 
            : "bg-indigo-600/10 hover:bg-indigo-600 text-indigo-400 hover:text-white border-indigo-500/20 hover:border-indigo-500 shadow-indigo-900/10";
        
        const tooltip = isReapply ? 'title="Action required: Please reapply for this issue"' : "";

        // Create modern action pills with individual entrance animations
        html += `
            <button id="${idPrefix}_${dmat_num}_${issue.companyShareId}" 
                    onclick="${issue.btnType}('${dmat_num}','${issue.companyShareId}');"
                    ${tooltip}
                    class="animate-in zoom-in slide-in-from-top-1 duration-300 fill-mode-both
                           ${themeClasses}
                           px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider 
                           transition-all active:scale-95 shadow-sm"
                    style="animation-delay: ${index * 100}ms">
                ${issue.scrip} ${isReapply ? '<i class="fas fa-exclamation-circle ml-1"></i>' : ""}
            </button>`;
    });
    html += "</div>";
    $(btnContainer).html(html);
}

                // Handle error/status messages from JSON
                if (json.e_msg) {
                    $(logId).html(`
                        <div class="text-[10px] font-mono text-slate-500 border-l border-slate-700 pl-2 mt-1">
                            ${json.e_msg}
                        </div>
                    `);
                } else {
                    $(logId).empty();
                }
            } else {
                // Error handling for non-JSON or partial results
                let errorContent = (/{/.test(result)) ? result.split("{")[0] : result;
                $(logId).html(`
                    <div class="text-amber-500 text-[10px] font-mono p-1 italic">
                        ⚠️ ${errorContent.substring(0, 50)}...
                    </div>
                `);
            }
        },
        error: function(xhr, status, errorThrown) {
            $(logId).html(`<span class="text-red-500 text-[10px] font-mono uppercase">Network Error: ${errorThrown}</span>`);
        }
    });
}

function applyIPO(dmat_num, companyShareId) {
    const logId = '#log_' + dmat_num;
    const btnId = `#applyipo_${dmat_num}_${companyShareId}`;

    $.ajax({
        url: 'meroshare_applyipo.php?dmat_num=' + dmat_num + '&companyShareId=' + companyShareId,
        beforeSend: function() {
            // Disable button and show custom SVG loader
            $(btnId).prop('disabled', true).addClass('opacity-50 cursor-wait');
            $(btnId).prepend(`
                <svg id="spinner_${dmat_num}_${companyShareId}" class="animate-spin -ml-1 mr-2 h-3 w-3 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            `);
            
            // Live Status in Log
            $(logId).html(`
                <div class="flex items-center gap-2 text-[10px] text-blue-400 font-mono animate-pulse uppercase tracking-tighter">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.5)]"></span>
                    Executing_Order...
                </div>
            `);
        },
        success: function(result) {
            $(btnId).prop('disabled', false).removeClass('opacity-50 cursor-wait');
            $(`#spinner_${dmat_num}_${companyShareId}`).remove();

            if (IsJsonString(result) === true) {
                const json = JSON.parse(result);
                
                if (json.message === "Share has been applied successfully.") {
                    // SUCCESS: Premium Green Badge
                    $(logId).html(`
                        <div class="inline-flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-3 py-1 rounded-lg animate-in zoom-in duration-300">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                            </svg>
                            <span class="text-[10px] font-bold uppercase tracking-tight">Applied Successfully</span>
                        </div>
                    `);
                    // Optionally fade out the button since it's done
                    $(btnId).fadeOut(1000);
                } else {
                    // DANGER: High Visibility Red Alert
                    $(logId).html(`
                        <div class="inline-flex items-center gap-2 bg-red-500/10 border border-red-500/20 text-red-400 px-3 py-1 rounded-lg animate-bounce">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span class="text-[10px] font-bold tracking-tighter">${json.message}</span>
                        </div>
                    `);
                }
            } else {
                // Fallback for raw errors
                let cleanErr = (/{/.test(result)) ? result.split("{")[0] : result;
                $(logId).html(`<span class="text-amber-500 text-[10px] font-mono italic">System_Notice: ${cleanErr}</span>`);
            }
        },
        error: function(xhr, status, error) {
            $(`#spinner_${dmat_num}_${companyShareId}`).remove();
            $(logId).html(`<span class="text-red-500 text-[10px] font-bold">CRITICAL_FAIL: ${error}</span>`);
        }
    });
}

function generateStatusButtons(dmat_num) {
    $.ajax({
        url: 'meroshare_status.php?dmat_num=' + dmat_num + '',
        beforeSend: function() {
            $('#checkStatus_' + dmat_num).prepend('<i id="spinner_' + dmat_num + '" class="fas fa-spinner fa-spin"></i>');
        },
        success: function(result) {
            //console.log(result);
            if (IsJsonString(result) === true) {
                var json = JSON.parse(result);
                //console.log(json);
                var i;
                var html = "";
                for (i = 0; i < json.table.length; i++) {
                    html += '<button style="margin:5px 5px;" class="pure-button button-small-padding pure-button-secondary" id="checkstatuscompany' + i + 'checkStatus_' + dmat_num + '_' + json.table[i].applicantFormId + '" onclick="' + json.table[i].btnType + '(\'' + dmat_num + '\',\'' + json.table[i].applicantFormId + '\',\'' + json.table[i].scrip + '\',\'' + i + '\');">' + json.table[i].scrip + '</button><br>';
                    $('#checkstatusall' + i).html(json.table[i].scrip);
                }
                //if html is empty return to initial stage
                if (json.table.length < 1) {
                    //$('#btn_'+dmat_num).html(html);  
                } else {
                    $('#btn_' + dmat_num).html(html);
                }


                $('#log_' + dmat_num).html(error_msg(json.e_msg));
                $('#spinner_' + dmat_num).remove();
                //console.log(json.e_msg);

            } else {
                if (/{/.test(result) === true) {
                    var res = result.split("{");
                    $('#log_' + dmat_num).html(error_msg(res[0]));
                    $('#spinner_' + dmat_num).remove();
                } else {
                    $('#log_' + dmat_num).html(error_msg(result));
                    $('#spinner_' + dmat_num).remove();
                }
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            $('#log_' + dmat_num).html(error_msg(errorThrown));
            $('#spinner_' + dmat_num).remove();
        }
    });
}

function checkstatusallforlatest(i) {

    $('button[id^="checkstatuscompany' + i + '"]').trigger("click");
}


function generateStatusButtonsall() {

    $.ajax({
        url: 'json-api.php?type=loginall',
        beforeSend: function() {
            $('#generateStatusButtonsall_btn').prepend('<i id="spinner_generateStatusButtonsall_btn" class="fas fa-spinner fa-spin"></i>');
        },
        success: function(result) {
            //console.log(result); 
            var json = JSON.parse(result);
            //console.log(json);

            var i;
            for (i = 0; i < json.length; i++) {
                generateStatusButtons(json[i].dmat_num);

            };
            $('#spinner_generateStatusButtonsall_btn').remove();
        },
        error: function(jqXHR, textStatus, errorThrown) {
            //$('#log_'+dmat_num).html(errorThrown);
            $('#spinner_generateStatusButtonsall').remove();
        }

    });
}


function checkStatus(dmat_num, applicantFormId, scrip, i) {
    $.ajax({
        url: 'meroshare_checkStatus.php?dmat_num=' + dmat_num + '&applicantFormId=' + applicantFormId + '&scrip=' + scrip + '',
        beforeSend: function() {
            $('#checkstatuscompany' + i + 'checkStatus_' + dmat_num + '_' + applicantFormId).prepend('<i id="spinner_' + dmat_num + '_' + applicantFormId + '" class="fas fa-spinner fa-spin"></i>');
        },
        success: function(result) {
            //console.log(result);
            if (IsJsonString(result) === true) {
                var json = JSON.parse(result);
                //console.log(json);
                $('#log_' + dmat_num).html(json.e_msg);
                $('#spinner_' + dmat_num + '_' + applicantFormId).remove();
            } else {
                if (/{/.test(result) === true) {
                    var res = result.split("{");
                    $('#log_' + dmat_num).html(res[0]);
                    $('#spinner_' + dmat_num + '_' + applicantFormId).remove();
                } else {
                    $('#log_' + dmat_num).html(result);
                    $('#spinner_' + dmat_num + '_' + applicantFormId).remove();
                }
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            $('#log_' + dmat_num).html(errorThrown);
            $('#spinner_' + dmat_num + '_' + applicantFormId).remove();
        }


    });
}

function updateKitta(e) {
    var kitta = $('#kitta').val();
    //console.log(kitta);
    $.ajax({
        url: 'json-api.php?type=updateKitta&kitta=' + kitta + '',
        success: function(result) {
            //console.log(result);
            $('#log_updateKitta').html(result);

        }

    });

    //here I want to prevent default
    e = e || window.event;
    e.preventDefault();
}

function success_msg(msg) {
    return '<span class="success">' + msg + '</span>';
}

function error_msg(msg) {
    return '<span class="danger">' + msg + '</span>';
}
var ifConnected = window.navigator.onLine;
if (ifConnected) {
    //do nothing
    //alert('Connection available');
} else {
    window.location.href = "no-internet.php";
}
//idea
//sudo_log()function which will log data with timestamp to localstorage
//to display all log as terminal div

//myshare
function myshares(dmat_num) {

    $.ajax({
        url: 'meroshare_myshare.php?dmat_num=' + dmat_num + '',
        beforeSend: function() {
            $('#allshares_btn_' + dmat_num).prepend('<i id="spinner_' + dmat_num + '" class="fas fa-spinner fa-spin"></i>');
        },
        success: function(result) {
            //console.log(result);
            if (IsJsonString(result) === true) {
                var json = JSON.parse(result);

                if (json.table === true) {
                    if (json.resp.totalItems > 0) {
                        var i;
                        var table = '<small>Last Update: 0 seconds ago<table class="pure-table pure-table-bordered"><thead><tr class="nosearch"><th>Symbol</th><th>Kitta</th><th>LTP</th><th>Value</th><th style="text-align: right;">(<i class="fas fa-arrow-up success"></i>/<i class="fas fa-arrow-down danger"></i>)</th></tr></thead><tbody>';
                        for (i = 0; i < json.resp.totalItems; i++) {
                            var ltpval = json.resp.meroShareMyPortfolio[i].valueAsOfLastTransactionPrice;
                            var pcpval = json.resp.meroShareMyPortfolio[i].valueAsOfPreviousClosingPrice;
                            if (ltpval > pcpval) {
                                var percent = '<span class="success">' + (((ltpval - pcpval) / ltpval) * 100).toFixed(2) + ' % <i class="fas fa-arrow-up"></i></span>';
                            } else if (ltpval < pcpval) {
                                var percent = '<span class="danger">' + (((ltpval - pcpval) / ltpval) * 100).toFixed(2) + ' % <i class="fas fa-arrow-down"></i></span>';
                            } else {
                                var percent = "";
                            }

                            table += '<tr class="nosearch"><td  data-tippy-content="' + json.resp.meroShareMyPortfolio[i].scriptDesc + '">' + json.resp.meroShareMyPortfolio[i].script + '</td><td>' + json.resp.meroShareMyPortfolio[i].currentBalance + '</td><td>' + json.resp.meroShareMyPortfolio[i].lastTransactionPrice + '</td><td>' + json.resp.meroShareMyPortfolio[i].valueAsOfLastTransactionPrice + '</td><td style="text-align: right;">' + percent + '</td></tr>';
                        }
                        table += '</tbody></table><small>';
                        $('#sharetable_' + dmat_num).html(table);
                    } else {
                        $('#sharetable_' + dmat_num).html(error_msg(json.resp.e_msg));
                    }
                } else {
                    $('#sharetable_' + dmat_num).html(error_msg(json.resp.message));
                }



            } else {
                if (/{/.test(result) === true) {
                    var res = result.split("{");
                    $('#sharetable_' + dmat_num).html(error_msg(res[0]));
                } else {
                    $('#sharetable_' + dmat_num).html(error_msg(result));
                }

            }
            $('#spinner_' + dmat_num).remove();

        },
        error: function(jqXHR, textStatus, errorThrown) {
            $('#sharetable_' + dmat_num).html(error_msg(errorThrown));
            $('#spinner_' + dmat_num).remove();
        }

    });

}

function mysharesall() {
    // dmat_num_list_json
    $.ajax({
        url: 'json-api.php?type=dmat_num_list_json',
        beforeSend: function() {
            $('#mysharesall').prepend('<i id="spinner_mysharesall" class="fas fa-spinner fa-spin"></i>');
        },
        success: function(result) {
            if (IsJsonString(result) === true) {
                var json = JSON.parse(result);
                for (var i = 0; i < json.length; i++) {
                    myshares(json[i]);
                }

            } else {
                if (/{/.test(result) === true) {
                    var res = result.split("{");
                    //console.log(res[0]);
                } else {
                    //console.log(result);
                }

            }

            $('#spinner_mysharesall').remove();
        },
        error: function(jqXHR, textStatus, errorThrown) {
            //console.log(errorThrown);
            $('#spinner_mysharesall').remove();
        }

    });

}
//not working idk
function eye(dmat_num) {
    //console.log(dmat_num);  
    if ($("password_" + dmat_num).hasClass("hidetext")) {
        //console.log("hasclass");
        $("password_" + dmat_num).removeClass("hidetext");
        $("crnNumber_" + dmat_num).removeClass("hidetext");
        $("transactionPIN_" + dmat_num).removeClass("hidetext");
        $("eye_" + dmat_num).removeClass("fa-eye").addClass("fa-eye-slash");

    } else {
        //console.log("hasnotclass");
        $("password_" + dmat_num).addClass("hidetext");
        $("crnNumber_" + dmat_num).addClass("hidetext");
        $("transactionPIN_" + dmat_num).addClass("hidetext");
        $("eye_" + dmat_num).removeClass("fa-eye-slash").addClass("fa-eye");
    }



}
//todo allshare function to update all with one click
//FOR LOADING DIV
$(window).ready(function() {
    $('#loading').hide();
});
/*SEARCH TABLE FOR DATA AND EXCLUDE <tr>  WITH class="nosearch"*/
$(document).ready(function() {
    $("#table_search").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("tbody tr:not(.nosearch)").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
});



function plRefresh(dmat_num) {
    $.ajax({
        url: 'meroshare_pl.php?dmat_num=' + dmat_num,
        beforeSend: function() {
            $('#refresh-btn-pl-btn').prepend('<i id="spinner_pl" class="fas fa-spinner fa-spin"></i>');
        },
        success: function(result) {
            if (IsJsonString(result) === true) {

                var json = JSON.parse(result);
                var plTable = '<table style="width:100%" class="pure-table pure-table-bordered"><thead><tr><th>#</th><th>Symbol</th><th>Qty</th><th>Buy Rate</th><th>Sell Rate</th><th>Buy Amount</th><th>Sell Amount</th><th>Profit/(Loss)</th></tr></thead><tbody>';
                //console.log(json);
                var pl_t = 0;
                for (var i = 0; i < json.table.length; i++) {
                    var s_rate = json.table[i].contract.rate;
                    var b_rate = json.table[i].contract.obligation.wacc;
                    var qty = json.table[i].contract.quantity;
                    var b_amt = qty * b_rate;
                    var s_amt = qty * s_rate;

                    var pl = s_amt - b_amt;
                    pl_t += pl;
                    if (Math.sign(pl) > 0) {
                        pl = '<span class="success">' + pl.toFixed(2) + '</span>';
                    } else {
                        pl = '<span class="danger">' + pl.toFixed(2) + '</span>';
                    }



                    plTable += '<tr><td>' + (i + 1) + '</td><td>' + json.table[i].contract.obligation.scriptCode + '</td><td style="text-align:right;">' + qty + '</td><td style="text-align:right;">' + b_rate.toFixed(2) + '</td><td style="text-align:right;">' + s_rate.toFixed(2) + '</td><td style="text-align:right;">' + b_amt.toFixed(2) + '</td><td style="text-align:right;">' + s_amt.toFixed(2) + '</td><td style="text-align:right;">' + pl + '</td></tr>';
                }
                if (Math.sign(pl_t) > 0) {
                    pl_t = '<span class="success">' + pl_t.toFixed(2) + '</span>';
                } else {
                    pl_t = '<span class="danger">' + pl_t.toFixed(2) + '</span>';
                }
                plTable += '</tbody><thead><tr><th colspan="7" style="text-align:right;">Total</th><th  style="text-align:right;">' + pl_t + '</th></tr></thead></table>'
                $("#pl-table").html(plTable);
                //console.log(plTable);

            } else {
                if (/{/.test(result) === true) {
                    var res = result.split("{");
                    //console.log(res);
                } else {
                    //console.log(result);
                }

            }

            $('#spinner_pl').remove();
        },
        error: function(jqXHR, textStatus, errorThrown) {
            //console.log(errorThrown);
            $('#spinner_pl').remove();
        }

    });
}


function pl_select_fetch() {
    var dmat_num = $('#pl-select-menu').val();
    $("#refresh-btn-pl").html('<button style="margin-bottom:10px;" id="refresh-btn-pl-btn" onclick="plRefresh(\'' + dmat_num + '\');" value="' + dmat_num + '" class="pure-button pure-button-primary">Refresh</button>');
    $.ajax({
        url: 'json-api.php?type=fetch_pl&dmat_num=' + dmat_num,
        beforeSend: function() {
            $('#pl-select-menu').prepend('<i id="spinner_pl" class="fas fa-spinner fa-spin"></i>');
        },
        success: function(result) {
            if (IsJsonString(result) === true) {
                var json = JSON.parse(result);
                var plTable = '<table style="width:100%" class="pure-table pure-table-bordered"><thead><tr><th>#</th><th>Symbol</th><th>Qty</th><th>Buy Rate</th><th>Sell Rate</th><th>Buy Amount</th><th>Sell Amount</th><th>Profit/(Loss)</th></tr></thead><tbody>';
                //console.log(json);
                var pl_t = 0;
                for (var i = 0; i < json.table.length; i++) {
                    var s_rate = json.table[i].contract.rate;
                    var b_rate = json.table[i].contract.obligation.wacc;
                    var qty = json.table[i].contract.quantity;
                    var b_amt = qty * b_rate;
                    var s_amt = qty * s_rate;

                    var pl = s_amt - b_amt;
                    pl_t += pl;
                    if (Math.sign(pl) > 0) {
                        pl = '<span class="success">' + pl.toFixed(2) + '</span>';
                    } else {
                        pl = '<span class="danger">' + pl.toFixed(2) + '</span>';
                    }



                    plTable += '<tr><td>' + (i + 1) + '</td><td>' + json.table[i].contract.obligation.scriptCode + '</td><td style="text-align:right;">' + qty + '</td><td style="text-align:right;">' + b_rate.toFixed(2) + '</td><td style="text-align:right;">' + s_rate.toFixed(2) + '</td><td style="text-align:right;">' + b_amt.toFixed(2) + '</td><td style="text-align:right;">' + s_amt.toFixed(2) + '</td><td style="text-align:right;">' + pl + '</td></tr>';
                }
                if (Math.sign(pl_t) > 0) {
                    pl_t = '<span class="success">' + pl_t.toFixed(2) + '</span>';
                } else {
                    pl_t = '<span class="danger">' + pl_t.toFixed(2) + '</span>';
                }
                plTable += '</tbody><thead><tr><th colspan="7" style="text-align:right;">Total</th><th  style="text-align:right;">' + pl_t + '</th></tr></thead></table>'
                $("#pl-table").html(plTable);
                //console.log(plTable);

            } else {
                if (/{/.test(result) === true) {
                    var res = result.split("{");
                    //console.log(res);
                } else {
                    //console.log(result);
                }

            }

            $('#spinner_pl').remove();
        },
        error: function(jqXHR, textStatus, errorThrown) {
            //console.log(errorThrown);
            $('#spinner_pl').remove();
        }

    });
}

function update_wacc(dmat_num) {
    $.ajax({
        url: 'meroshare_update_wacc.php?dmat_num=' + dmat_num + '',
        beforeSend: function() {
            $('#update_wacc_' + dmat_num + '_btn').prepend('<i id="spinner_update_wacc_' + dmat_num + '_btn" class="fas fa-spinner fa-spin"></i>');
        },
        success: function(result) {
            //console.log(result);
            if (IsJsonString(result) === true) {
                var json = JSON.parse(result);
                var msg = json.e_msg;
                if (msg != "") {
                    $('#update_wacc_' + dmat_num).html(error_msg(msg));
                } else {
                    $('#update_wacc_' + dmat_num).html('done! <a href="#' + dmat_num + '_edis_modal" rel="modal:open">info</a>');
                    var edis_modal = '<div id="' + dmat_num + '_edis_modal" class="modal">' + json.s_msg + '<br><a href="#" rel="modal:close">Close</a></div>';
                    $("#edis_modal").html(edis_modal);
                }

            } else {
                $('#update_wacc_' + dmat_num).html(result);
            }
            $('#spinner_update_wacc_' + dmat_num + '_btn').remove();

        }


    });

}

function update_wacc_all() {

    $.ajax({
        url: 'json-api.php?type=loginall',
        beforeSend: function() {
            $('#update_wacc_all_btn').prepend('<i id="spinner_update_wacc_all_btn" class="fas fa-spinner fa-spin"></i>');
        },
        success: function(result) {
            //console.log(result); 
            var json = JSON.parse(result);
            //console.log(json);

            var i;
            for (i = 0; i < json.length; i++) {
                update_wacc(json[i].dmat_num);

            };
            $('#spinner_update_wacc_all_btn').remove();
        },
        error: function(jqXHR, textStatus, errorThrown) {
            //$('#log_'+dmat_num).html(errorThrown);
            $('#spinner_update_wacc_all_btn').remove();
        }

    });
}

function create_backup() {
    $.ajax({
        url: 'json-api.php?type=create_backup',
        beforeSend: function() {
            $('#create_bakup').prepend('<i id="spinner_create_backup" class="fas fa-spinner fa-spin"></i>');
        },
        success: function(result) {

            //on success refresh bakup table
            backup_table();
            $('#spinner_create_backup').remove();

        }


    });
}

function backup_table() {
    $.ajax({
        url: 'json-api.php?type=backup_table',
        success: function(result) {
            console.log(result);
            $('#backup_table_body').html(result);

        }
    });


}


function restore_backup(t) {


    $.ajax({
        url: 'json-api.php?type=restore_backup&t=' + t,
        success: function(result) {
            if (result == "success") {
                var msg = success_msg("Successfully Restored Database!");
                $('#log_' + t).html(msg);
            } else {
                alert_box("danger", "Error While Restoring Database!");
            }


        }
    });


}

function delete_backup(t) {


    $.ajax({
        url: 'json-api.php?type=delete_backup&t=' + t,
        success: function(result) {
            $('#log_' + t).html(result);
            backup_table();


        }
    });


}


$('#msbak_upload_btn').on('click', function(e) {
    e.preventDefault();
    var file_data = $('#msbak_file').prop('files')[0];
    var form_data = new FormData();
    form_data.append('msbak_file', file_data);
    //alert(form_data);                             
    $.ajax({
        url: 'upload_backup.php', // <-- point to server-side PHP script 
        dataType: 'text', // <-- what to expect back from the PHP script, if anything
        cache: false,
        contentType: false,
        processData: false,
        data: form_data,
        type: 'post',
        success: function(res) {
            $('#progress_report').html(res);
            backup_table();
        }
    });

});


function ipo_result_company_names() {


    $.ajax({
        url: 'json-api.php?type=ipo_result_company_names',
        success: function(result) {
            $('#company_names_options').html(result);
            var cno = $("#company_names_options").val();
            put_ipo_script_value_to_table(cno);


        }
    });


}


function put_ipo_script_value_to_table(d) {

    dx = d.split("_")[2];
    $("[id=checkIpoResult_script]").html(dx);
}


$("#company_names_options").change(function() {
    var cno = $("#company_names_options").val();
    put_ipo_script_value_to_table(cno);
});

function checkIpoResult_ms(dmat_num) {
    $('#checkIpoResult_ms_' + dmat_num).prepend('<i id="spinner" class="fas fa-spinner fa-spin"></i>');
    $.ajax({
        url: 'json-api.php?type=loadcaptcha_iporesult&dmat_num=' + dmat_num,
        success: function(result) {

            var obj = JSON.parse(result);
            var captcha = obj.body.captchaData.captcha;
            var captchaIdentifier = obj.body.captchaData.captchaIdentifier;
            solve_captcha(captcha, captchaIdentifier, dmat_num);




        }
    });




}






function checkIpoResult_db() {
    var value = $('#company_names_options').val();
    $('#checkIpoResult_db_id').prepend('<i id="spinner" class="fas fa-spinner fa-spin"></i>');
    $.ajax({
        url: 'json-api.php?type=checkIpoResult_db&value=' + value,
        success: function(result) {
            //process result
            var json = JSON.parse(result);
            var i;
            for (i = 0; i < json.length; i++) {

                var dmat_num = json[i]["dmat_num"];
                var log = json[i]["log"];

                if (log.replace(/\s/g, '').toLowerCase() == "sorry,notallotedfortheenteredboid.") {
                    $('#log_' + dmat_num).removeClass("danger warning success").addClass("danger").html(log);
                } else if (log.replace(/\s/g, '').toLowerCase() == "congratulationalloted!!!allotedquantity:10") {
                    $('#log_' + dmat_num).removeClass("danger warning success").addClass("success").html(log);
                } else {
                    $('#log_' + dmat_num).removeClass("danger warning success").addClass("warning").html(log);
                }



            };

            //resultend
            $('#spinner').remove();
        }
    });
}


function checkIpoResult_ms_all() {
    $('#checkIpoResult_ms_all_id').prepend('<i id="spinner" class="fas fa-spinner fa-spin"></i>');
    //dmat_num_list_json
    $.ajax({
        url: 'json-api.php?type=dmat_num_list_json',
        success: function(result) {

            var json = JSON.parse(result);
            var i;
            for (i = 0; i < json.length; i++) {

                checkIpoResult_ms(json[i]);
            }
            $('#spinner').remove();
        }
    });


}

function force_fetch_ipo_result_func() {
    var value = $('#company_names_options').val();
    $('#force_fetch_ipo_result').prepend('<i id="spinner" class="fas fa-spinner fa-spin"></i>');
    $.ajax({
        url: 'json-api.php?type=force_fetch_ipo_result&value=' + value,
        success: function(result) {
            $('#ipo-result-table-body').html(result);
            $('#spinner').remove();
        }
    });
}


function sortTable(tid, tdn) {
    var table, rows, switching, i, x, y, shouldSwitch;
    table = document.getElementById(tid);
    switching = true;
    /* Make a loop that will continue until
    no switching has been done: */
    while (switching) {
        // Start by saying: no switching is done:
        switching = false;
        rows = table.rows;
        /* Loop through all table rows (except the
        first, which contains table headers): */
        for (i = 1; i < (rows.length - 1); i++) {
            // Start by saying there should be no switching:
            shouldSwitch = false;
            /* Get the two elements you want to compare,
            one from current row and one from the next: */
            x = rows[i].getElementsByTagName("TD")[tdn];
            y = rows[i + 1].getElementsByTagName("TD")[tdn];
            // Check if the two rows should switch place:
            if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) {
                // If so, mark as a switch and break the loop:
                shouldSwitch = true;
                break;
            }
        }
        if (shouldSwitch) {
            /* If a switch has been marked, make the switch
            and mark that a switch has been done: */
            rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
            switching = true;
        }
    }
}


function reapplyIPO(dmat_num, companyShareId) {
    const logId = '#log_' + dmat_num;
    const btnId = `#reapplyipo_${dmat_num}_${companyShareId}`;

    $(btnId).prop('disabled', true).addClass('opacity-50 cursor-wait');
    $(btnId).prepend(`
        <svg id="spinner_re_${dmat_num}" class="animate-spin -ml-1 mr-2 h-3 w-3 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    `);
    
    $(logId).html(`
        <div class="flex items-center gap-2 text-[10px] text-indigo-400 font-mono animate-pulse uppercase tracking-tighter">
            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 shadow-[0_0_8px_rgba(99,102,241,0.5)]"></span>
            Syncing_&_Re_Applying...
        </div>
    `);

    $.ajax({
        url: 'meroshare_reapplyipo.php',
        type: 'POST',
        data: { 
            dmat_num: dmat_num,
            companyShareId: companyShareId
        },
        success: function(result) {
            $(btnId).prop('disabled', false).removeClass('opacity-50 cursor-wait');
            $(`#spinner_re_${dmat_num}`).remove();

            try {
                const json = JSON.parse(result);
                if (json.statusCode === 201 || json.status === "CREATED" || json.message === "Share has been applied successfully.") {
                    $(logId).html(`
                        <div class="inline-flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-3 py-1 rounded-lg animate-in zoom-in duration-300">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                            <span class="text-[10px] font-bold uppercase tracking-tight">Re-Applied Successfully</span>
                        </div>
                    `);
                    $(btnId).fadeOut(1000);
                } else {
                    $(logId).html(`
                        <div class="inline-flex items-center gap-2 bg-red-500/10 border border-red-500/20 text-red-400 px-3 py-1 rounded-lg animate-bounce">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                            <span class="text-[10px] font-bold tracking-tighter">${json.message || "Re-application failed"}</span>
                        </div>
                    `);
                }
            } catch (e) {
                $(logId).html(`<span class="text-amber-500 text-[10px] font-bold">PARSE_FAIL: Invalid Server Response</span>`);
            }
        },
        error: function(xhr, status, error) {
            $(btnId).prop('disabled', false).removeClass('opacity-50 cursor-wait');
            $(`#spinner_re_${dmat_num}`).remove();
            $(logId).html(`<span class="text-red-500 text-[10px] font-bold">CRITICAL_FAIL: ${error}</span>`);
        }
    });
}
     //convert ipo result table table to image            
function convertIpoResultTableToImage() {
            var body = document.getElementById("iporesultimagediv");
            html2canvas(document.getElementById("ipo-result-table-body"), {
                onrendered: function(canvas) {
                    var img = canvas.toDataURL("image/jpeg");
                    body.innerHTML = '<a style="display:none;" id="iporesultimage" download="IPO Result.jpeg" href="'+img+'">Click To Download</a>';
                    document.getElementById("iporesultimage").click();
                    }
            });
         }        

         // Step 1: Discover IPOs this user applied for
function syncUserHistory(dmat) {
    const btn = $(`#sync_btn_${dmat}`);
    const log = $(`#log_${dmat}`);
    
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    log.html('<span class="text-blue-400 animate-pulse text-[10px]">Fetching History...</span>');

    $.getJSON(`sync_ipo_history.php?dmat_num=${dmat}`, function(data) {
        btn.prop('disabled', false).html('<i class="fas fa-check text-emerald-400"></i> Done');
        
        // Show the most recent Scrip in the Symbol column
        if(data.length > 0) {
            const latest = data[0]; // Assuming newest is first
            $(`#scrip_pill_${dmat}`).html(`
                <span class="bg-slate-700 text-slate-200 px-2 py-0.5 rounded text-[10px] font-bold">
                    ${latest.scrip}
                </span>
            `);
            
            // Show the status from our local DB
            renderStatusPill(dmat, latest);
        }
    });
}

// Step 2: Render the Allotment Status or "Check" button
function renderStatusPill(dmat, item) {
    const container = $(`#status_container_${dmat}`);
    
    if (item.statusName === "Never Checked" || item.statusName === "Not Alloted" || item.statusName === "Alloted") {
        let colorClass = "text-slate-400 bg-slate-800";
        if (item.statusName === "Alloted") colorClass = "text-emerald-400 bg-emerald-500/10 border-emerald-500/20";
        if (item.statusName === "Not Alloted") colorClass = "text-red-400 bg-red-500/10 border-red-500/20";

        container.html(`
            <div class="flex items-center gap-2">
                <span class="px-2 py-1 rounded border ${colorClass} text-[10px] font-bold uppercase">
                    ${item.statusName} ${item.receivedKitta > 0 ? '('+item.receivedKitta+')' : ''}
                </span>
                <button onclick="refreshSingleResult('${dmat}', '${item.applicantFormId}', '${item.scrip}')" 
                        class="text-slate-500 hover:text-blue-400 transition-colors">
                    <i class="fas fa-sync-alt text-[10px]"></i>
                </button>
            </div>
        `);
    }
}

// Step 3: Hit Meroshare for the final Allotment result
function refreshSingleResult(dmat, formId, scrip) {
    $(`#log_${dmat}`).html('<i class="fas fa-spinner fa-spin text-blue-500"></i>');
    
    $.getJSON(`refresh_single_result.php?dmat_num=${dmat}&formId=${formId}&scrip=${scrip}`, function(updatedItem) {
        renderStatusPill(dmat, updatedItem);
    });
}

function updateResultDisplay(dmat, items) {
    const log = $(`#log_${dmat}`);
    const dataArray = Array.isArray(items) ? items : [items];
    
    let html = `
        <table class="w-full border-separate border-spacing-y-1">
            <thead>
                <tr class="text-[8px] font-black text-slate-600 uppercase tracking-widest">
                    <th class="text-left pb-1 pl-2 w-16">Scrip</th>
                    <th class="text-left pb-1">Status</th>
                    <th class="text-center pb-1">Units</th>
                    <th class="text-right pb-1 pr-3">Sync Time</th>
                    <th class="w-6"></th>
                </tr>
            </thead>
            <tbody>
    `;

    dataArray.forEach(item => {
        let color = "text-slate-400 bg-slate-800 border-slate-700";
        let dot = "bg-slate-500";
       // let timeLabel = item.last_updated ? item.last_updated.split(' ')[1] : "--:--";

        // USE THE NEW HELPER HERE
    let relativeTime = timeAgo(item.last_updated);

        if (item.statusName === "Alloted") {
    color = "text-emerald-400 bg-emerald-500/10 border-emerald-400/20";
    dot = "bg-emerald-500 shadow-[0_0_5px_rgba(16,185,129,0.4)]";
} else if (item.statusName === "Not Alloted") {
    color = "text-rose-400 bg-rose-500/10 border-rose-400/20";
    dot = "bg-rose-500";
} else if (item.statusName === "Never Checked") {
    color = "text-amber-400/70 bg-amber-400/5 border-amber-400/10";
    dot = "bg-amber-400";
    relativeTime = "Never";
} else if (item.statusName === "Rejected") {
    // High Risk: Using Orange with a Pulse animation
    color = "text-orange-400 bg-orange-500/10 border-orange-500/40 ring-1 ring-orange-500/20";
    dot = "bg-orange-500 animate-pulse shadow-[0_0_8px_rgba(249,115,22,0.6)]";
} else if (item.statusName === "Unverified") {
    // Medium Risk: Indigo/Slate mix
    color = "text-indigo-300 bg-indigo-500/5 border-indigo-400/20";
    dot = "bg-indigo-400 shadow-[0_0_3px_rgba(129,140,248,0.3)]";
}

        html += `
            <tr class="group bg-slate-900/40 hover:bg-slate-800/40 transition-colors">
                <td class="py-1.5 pl-2 text-[10px] font-bold text-slate-200 border-y border-l border-slate-800 rounded-l-lg">${item.scrip}</td>
                <td class="py-1.5 border-y border-slate-800">
                    <span class="px-1.5 py-0.5 rounded border ${color} text-[8px] font-bold uppercase flex items-center gap-1 w-fit">
                        <span id="status-${item.scrip}-dot-${dmat}" class="w-1 h-1 rounded-full ${dot}"></span>
                        ${item.statusName}
                    </span>
                </td>
                <td class="py-1.5 text-center text-[10px] font-mono text-slate-400 border-y border-slate-800">
                    ${item.receivedKitta > 0 ? item.receivedKitta : '-'}
                </td>
                <td class="py-1.5 text-right text-[9px] font-medium text-slate-500 pr-3 border-y border-slate-800 tabular-nums">
                    ${relativeTime}
                </td>
                <td class="py-1.5 text-right pr-2 border-y border-r border-slate-800 rounded-r-lg">
                    <button id="check-btn-${item.scrip}-dot-${dmat}" onclick="msCheckResult('${dmat}', '${item.applicantFormId}', '${item.scrip}')" 
                            class="text-slate-600 hover:text-blue-400 transition-all cursor-pointer">
                        <i class="fas fa-sync-alt text-[9px]"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    html += `</tbody></table>`;
    log.html(html);
}

// THE KEY FIX: After checking 1 result, re-run syncHistory to show the WHOLE list again
function msCheckResult(dmat, formId, scrip) {
    // Show a small loader inside the row if possible, or just re-sync
    $(`#log_${dmat}`).find(`button`).addClass('fa-spin text-blue-500');
    
    $.getJSON(`refresh_single_result.php?dmat_num=${dmat}&formId=${formId}&scrip=${scrip}`, function(response) {
        // Now, instead of rendering just 'response', we fetch the full history from DB
        syncHistory(dmat); 
    });
}

function timeAgo(timestamp) {
    if (!timestamp || timestamp === "Never") return "Never";
    
    // Handle SQL format (YYYY-MM-DD HH:MM:SS)
    const date = new Date(timestamp.replace(/-/g, "/"));
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);

    if (seconds < 60) return seconds + "s ago";
    
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return minutes + "m ago";
    
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return hours + "h ago";
    
    const days = Math.floor(hours / 24);
    if (days < 30) return days + "d ago";

    const months = Math.floor(days / 30);
    if (months < 12) return months + "mo ago";

    const years = Math.floor(months / 12);
    return years + "y ago";
}

function showSentinelModal(title, message, type = 'error') {
    // Define colors based on type
    const colors = {
        error: { bg: 'bg-rose-500/10', border: 'border-rose-500/20', text: 'text-rose-400', icon: 'fa-exclamation-circle' },
        success: { bg: 'bg-emerald-500/10', border: 'border-emerald-500/20', text: 'text-emerald-400', icon: 'fa-check-circle' },
        info: { bg: 'bg-blue-500/10', border: 'border-blue-500/20', text: 'text-blue-400', icon: 'fa-info-circle' }
    };

    const theme = colors[type] || colors.error;

    const modalHtml = `
        <div id="sentinel-modal" class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm transition-opacity">
            <div class="bg-[#161b22] border border-slate-800 w-full max-w-sm rounded-2xl shadow-2xl transform transition-all scale-100 p-6">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 rounded-xl ${theme.bg} border ${theme.border} flex items-center justify-center">
                        <i class="fas ${theme.icon} ${theme.text} text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-white font-bold tracking-tight">${title}</h3>
                        <p class="text-slate-500 text-xs uppercase tracking-widest font-semibold">System Notice</p>
                    </div>
                </div>
                
                <div class="text-slate-300 text-sm leading-relaxed mb-6">
                    ${message}
                </div>

                <button onclick="closeSentinelModal()" 
                        class="w-full py-3 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold uppercase tracking-widest rounded-xl transition-all active:scale-95">
                    Close
                </button>
            </div>
        </div>
    `;

    // Append to body
    $('body').append(modalHtml);
}

function closeSentinelModal() {
    const isSuccess = $('#sentinel-modal h3').text().includes("Successful");
    $('#sentinel-modal').fadeOut(200, function() {
        $(this).remove();
        if (isSuccess) {
            location.reload(); // Optional: Reload to show the new user in the table
        }
    });
}

function showConfirmationModal(title, message, confirmCallback) {
    // 1. Setup Content
    $('#modalTitle').text(title);
    $('#modalMessage').html(message);
    
    // 2. Build Decision Buttons
    // We use .off() to ensure no old click events are lingering
    $('#modalFooter').empty().off('click'); 
    
    $('#modalFooter').html(`
        <button onclick="closeSentinelModal()" class="px-4 py-2 text-xs font-bold text-slate-400 hover:text-white transition-colors">
            CANCEL
        </button>
        <button id="confirmExecuteBtn" class="px-6 py-2 bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold rounded-xl transition-all shadow-lg shadow-rose-900/20">
            YES, PROCEED
        </button>
    `);

    // 3. Bind the specific action
    $('#confirmExecuteBtn').one('click', function() {
        if (typeof confirmCallback === "function") {
            confirmCallback();
        }
        closeSentinelModal();
    });

    // 4. Reveal Modal
    $('#sentinelModal').removeClass('hidden').addClass('flex');
}

function openEditModal(id, name, pass, crn, pin, isactive) {
    const modalHtml = `
        <div id="sentinel-modal" class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-[#161b22] border border-blue-500/30 w-full max-w-md rounded-2xl shadow-2xl p-6">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-white font-bold tracking-tight">Modify Credentials</h3>
                        <p class="text-[9px] text-blue-500 uppercase tracking-widest font-black">ID: ${id}</p>
                    </div>
                    <button onclick="closeSentinelModal()" class="text-slate-500 hover:text-white transition-colors"><i class="fas fa-times"></i></button>
                </div>
                
                <form id="editUserForm" class="space-y-4">
                    <input type="hidden" name="id" value="${id}">
                    <input type="hidden" name="action" value="update_user">
                    
                    <div>
                        <label class="text-[10px] text-slate-500 uppercase font-bold pl-1">Full Name</label>
                        <input type="text" name="name" value="${name}" required 
                               class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-slate-200 outline-none focus:border-blue-500/50 transition-colors">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] text-slate-500 uppercase font-bold pl-1">MeroShare Pass</label>
                            <input type="text" name="password" value="${pass}" required 
                                   class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-slate-200 outline-none focus:border-blue-500/50 transition-colors">
                        </div>
                        <div>
                            <label class="text-[10px] text-slate-500 uppercase font-bold pl-1">CRN Number</label>
                            <input type="text" name="crnNumber" value="${crn}" required 
                                   class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-slate-200 outline-none focus:border-blue-500/50 transition-colors">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] text-slate-500 uppercase font-bold pl-1">Transaction PIN</label>
                            <input type="text" name="transactionPIN" value="${pin}" required maxlength="4" 
                                   class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-slate-200 outline-none focus:border-blue-500/50 transition-colors text-center tracking-widest font-mono">
                        </div>
                        <div class="flex flex-col justify-center pl-2">
                            <label class="text-[10px] text-slate-500 uppercase font-bold mb-2">Account Status</label>
                            <label class="relative inline-flex items-center cursor-pointer group">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" class="sr-only peer" ${isactive == 1 ? 'checked' : ''}>
                                <div class="w-11 h-6 bg-slate-800 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-slate-400 peer-checked:after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500 shadow-inner border border-slate-700 peer-checked:border-emerald-400"></div>
                                <span class="ml-3 text-[11px] font-bold text-slate-500 uppercase tracking-widest peer-checked:text-emerald-400 transition-colors">Active</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex gap-3 mt-6">
                        <button type="button" onclick="closeSentinelModal()" 
                                class="flex-1 py-3 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold uppercase tracking-widest rounded-xl transition-all">
                            Discard
                        </button>
                        <button type="submit" 
                                class="flex-1 py-3 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold uppercase tracking-widest rounded-xl transition-all shadow-lg shadow-blue-600/20">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    $('body').append(modalHtml);

    // Submission Logic
    $('#editUserForm').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize();
        const btn = $(this).find('button[type="submit"]');
        
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Updating...');

        $.getJSON('json-api.php', formData, function(response) {
            if(response.status === 'success') {
                closeSentinelModal();
                showSentinelModal("User Details Updated", response.message, "success");
                setTimeout(() => location.reload(), 1200);
            } else {
                showSentinelModal("Error", response.message, "error");
                btn.prop('disabled', false).text('Save Changes');
            }
        });
    });
}
function eye(dmat) {
    // Select the three elements for this specific row
    const elements = [
        $(`#password_${dmat}`),
        $(`#crnNumber_${dmat}`),
        $(`#transactionPIN_${dmat}`)
    ];
    
    const icon = $(`#eye_${dmat}`);

    // Check the current state based on the icon class
    if (icon.hasClass('fa-eye')) {
        // UNHIDE: Remove blur, change icon
        elements.forEach(el => el.addClass('show-text'));
        icon.removeClass('fa-eye').addClass('fa-eye-slash').addClass('text-blue-400');
    } else {
        // HIDE: Add blur back, revert icon
        elements.forEach(el => el.removeClass('show-text'));
        icon.removeClass('fa-eye-slash').removeClass('text-blue-400').addClass('fa-eye');
    }
}

/**
 * Toggles the visibility of the stored password input
 */
function togglePassVisibility() {
    const field = document.getElementById('pass-field');
    const icon = document.getElementById('eye-icon');
    
    if (field.type === "password") {
        field.type = "text";
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        field.type = "password";
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

/**
 * Generates a compliant 8-char password: 
 * 2 Caps, 2 Numbers, @, #, 2 Small
 */
function generateCompliantPassword() {
    const caps = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    const smalls = "abcdefghijklmnopqrstuvwxyz";
    const nums = "0123456789";
    
    let pass = "";
    // Pick 2 random Caps
    pass += caps[Math.floor(Math.random() * caps.length)];
    pass += caps[Math.floor(Math.random() * caps.length)];
    // Pick 2 random Nums
    pass += nums[Math.floor(Math.random() * nums.length)];
    pass += nums[Math.floor(Math.random() * nums.length)];
    // Add required specials
    pass += "@";
    // Pick 2 random Smalls
    pass += smalls[Math.floor(Math.random() * smalls.length)];
    pass += smalls[Math.floor(Math.random() * smalls.length)];
    pass += smalls[Math.floor(Math.random() * smalls.length)];
    pass += smalls[Math.floor(Math.random() * smalls.length)];
    
    // Optional: Shuffle the string so the pattern isn't predictable
    return pass.split('').sort(() => 0.5 - Math.random()).join('');
}

/**
 * Triggered when "Change" button is clicked
 */
function initPasswordChange(dmat) {
    const suggestedPass = getSuggestedPassword(userConfig.password, userConfig.name);
    // Pull the current password from our global config object
    const currentPass = userConfig.password;
    
    const modalHtml = `
        <div class="p-6">
            <h3 class="text-lg font-bold text-white mb-4 uppercase tracking-tight">Rotate Credentials</h3>
            <div class="space-y-4">
                <div>
                    <label class="text-[10px] text-slate-500 uppercase font-black block mb-1">Current Password</label>
                    <!-- Pre-filled with existing password from database -->
                    <input type="text" id="old-pass" value="${currentPass}" 
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-sm text-slate-400 font-mono outline-none focus:border-blue-500">
                </div>
                
                <div class="p-4 bg-blue-500/5 border border-blue-500/10 rounded-2xl">
                    <div class="flex justify-between items-center mb-3">
                        <p class="text-[9px] text-blue-500 uppercase font-black">Suggested New Password</p>
                        <button onclick="refreshSuggestedPass()" class="text-[9px] text-slate-500 hover:text-white transition-colors">
                            <i class="fas fa-redo-alt"></i> Regerate
                        </button>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <input type="text" id="new-pass" value="${suggestedPass}" 
                                class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-xs text-emerald-400 font-mono outline-none">
                        </div>
                        <div>
                            <input type="text" id="confirm-pass" value="${suggestedPass}" 
                                class="w-full bg-slate-900 border border-slate-800 rounded-lg px-3 py-2 text-xs text-emerald-400 font-mono outline-none">
                        </div>
                    </div>
                </div>

                <div class="pt-2">
                    <button onclick="processPasswordRotation('${dmat}')" id="btn-rotate" 
                        class="w-full bg-blue-600 hover:bg-blue-500 text-white font-black py-3 rounded-xl text-xs uppercase tracking-widest transition-all">
                        Update in MeroShare & Vault
                    </button>
                </div>
            </div>
        </div>
    `;
    
    showSentinelModal("Security Protocol", modalHtml, "info");
}

/**
 * This Was Built to make password rotation easier; so that if manual login is needed, we will always know 
 * it is either of them.
 */
function getSuggestedPassword(currentPass, name = 'Mero Share') {
    // Extract the first word from the name string, fallback to 'Adonis' if empty
    const prefix = name.trim().split(' ')[0] || 'Mero';
    
    // Set up the dynamic toggle variants
    const pass1 = `${prefix}@1234`; // Dynamic part based on the name
    const pass2 = `${prefix}@4321`; // Kept static per your explicit mapping logic

    if (currentPass === pass1) {
        return pass2;
    } else if (currentPass === pass2) {
        return pass1;
    } else {
        return pass1;
    }
}
/**
 * Small helper to regenerate only the new pass fields if the user 
 * doesn't like the first suggestion.
 */
function refreshSuggestedPass() {
    const freshPass = generateCompliantPassword();
    $('#new-pass').val(freshPass);
    $('#confirm-pass').val(freshPass);
}

// 2. The Rotation Function (Keep this OUTSIDE of $(document).ready)
function processPasswordRotation(dmat) {
    console.log("Rotating for DMAT:", dmat);
    const oldPass = $('#old-pass').val();
    const newPass = $('#new-pass').val();
    const confirmPass = $('#confirm-pass').val();

    if (newPass !== confirmPass) {
        showSentinelModal("Validation Error", "New passwords do not match.", "error");
        return;
    }

    $('#btn-rotate').prop('disabled', true).text('PROCESSING...');

    $.ajax({
        url: 'json-api.php?action=change_password',
        type: 'POST',
        data: {
            dmat: String(dmat).trim(),
            oldPassword: oldPass,
            newPassword: newPass,
            confirmPassword: confirmPass
        },
        dataType: 'json',
        success: function(res) {
            if (res.status === "success") {
                showSentinelModal("Success", "Password updated successfully.", "success");
                setTimeout(() => location.reload(), 2000);
            } else {
                showSentinelModal("Error", res.message, "error");
                $('#btn-rotate').prop('disabled', false).text('Update in MeroShare & Vault');
            }
        },
        error: function() {
            showSentinelModal("System Error", "Communication failure with the API server.", "error");
            $('#btn-rotate').prop('disabled', false).text('Update in MeroShare & Vault');
        }
    });
}

// ==========================================
// SYSTEM COMPONENT: BS DATE ENGINE (TEXT DATA ENTRY)
// ==========================================
$(document).ready(function() {
    initializeNepaliDatePicker();
});
    function getNepaliDateString(targetAD) {
            // Precise 10-Year Month Matrix Dataset (BS 2083 - BS 2093)
    const nepaliYearDays = {
        2083: [31, 31, 32, 32, 31, 30, 30, 30, 29, 30, 29, 30],
        2084: [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
        2085: [31, 32, 31, 32, 31, 30, 31, 29, 30, 29, 30, 30],
        2086: [31, 31, 32, 32, 31, 30, 30, 30, 29, 30, 30, 30],
        2087: [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
        2088: [31, 32, 31, 32, 31, 30, 31, 29, 30, 29, 30, 30],
        2089: [31, 31, 32, 32, 31, 30, 30, 30, 29, 30, 29, 32],
        2090: [31, 31, 32, 32, 31, 30, 30, 30, 29, 30, 30, 30],
        2091: [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
        2092: [31, 32, 31, 32, 31, 30, 31, 29, 30, 29, 30, 30],
        2093: [31, 31, 32, 32, 31, 30, 30, 30, 29, 30, 30, 30]
    };

        // Reference Anchor Point: Gregorian 2026-04-14 matches Nepali 2083-01-01
    const anchorAD = new Date("2026-04-14");
    const anchorBSYear = 2083;

        let diffTime = targetAD.getTime() - anchorAD.getTime();
        let diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

        let currentBSYear = anchorBSYear;
        let currentBSMonth = 0; // Baishakh (Index 0)
        let currentBSDay = 1;

        if (diffDays >= 0) {
            while (diffDays > 0) {
                let daysInMonth = nepaliYearDays[currentBSYear][currentBSMonth];
                if (diffDays >= daysInMonth) {
                    diffDays -= daysInMonth;
                    currentBSMonth++;
                    if (currentBSMonth > 11) {
                        currentBSMonth = 0;
                        currentBSYear++;
                    }
                } else {
                    currentBSDay += diffDays;
                    diffDays = 0;
                }
            }
        } else {
            return "2083.01.01"; // Default backup fallback
        }

        // Format to exact double-digit layout string: YYYY.MM.DD
        let mm = String(currentBSMonth + 1).padStart(2, '0');
        let dd = String(currentBSDay).padStart(2, '0');
        return `${currentBSYear}.${mm}.${dd}`;
    }
function initializeNepaliDatePicker() {


    // Get today's translated string
    let todayNepaliDate = getNepaliDateString(new Date());

    // Insert the text value into all fields with the class target
    $("#nepali-date-pick").each(function() {
        if (!$(this).val()) {
            $(this).val(todayNepaliDate);
        }
    });



}


async function triggerCleanExcelImageDownload(jsonData) {
    // 1. Define the dateStamp
    let dateStamp = new Date().toLocaleDateString('en-GB');
    
    // 2. Calculate totals
    let sumNet = 0, sumM = 0, sumC = 0, sumA = 0;
    let matrixRowsHtml = "";

    jsonData.forEach(req => {
        // Accessing the new keys from your JSON structure
        let net = parseFloat(req.withdrawAmount) || 0;
        let m = parseFloat(req.updatedManagerCut) || 0;
        let c = parseFloat(req.totalC) || 0;
        let a = parseFloat(req.totalA) || 0;

        sumNet += net;
        sumM += m;
        sumC += c;
        sumA += a;

        matrixRowsHtml += `
            <tr style="border-bottom: 1px solid #111111;">
                <td style="padding: 12px 10px; font-weight: bold; border: 1px solid #111111; color: #000000; font-family: Arial, sans-serif;">${req.name}</td>
                <td style="padding: 12px 10px; border: 1px solid #111111; font-family: monospace; color: #000000;">${req.dmat}</td>
                <td style="padding: 12px 10px; text-align: right; border: 1px solid #111111; font-family: monospace; color: #000000; font-weight: bold;">${net.toFixed(2)}</td>
                <td style="padding: 12px 10px; text-align: right; border: 1px solid #111111; font-family: monospace; color: #000000;">${c.toFixed(2)}</td>
                <td style="padding: 12px 10px; text-align: right; border: 1px solid #111111; font-family: monospace; color: #000000;">${a.toFixed(2)}</td>
                <td style="padding: 12px 10px; text-align: right; border: 1px solid #111111; font-family: monospace; color: #000000;">${m.toFixed(2)}</td>
            </tr>
        `;
    });

    // 3. Create container
    let snapshotContainer = document.createElement('div');
    snapshotContainer.id = "pure-image-canvas";
    snapshotContainer.setAttribute('style', `
        position: absolute !important; 
        top: 0 !important; 
        left: 0 !important; 
        width: 800px !important; 
        background: #ffffff !important; 
        padding: 30px !important; 
        box-sizing: border-box !important;
        z-index: 9999 !important;
    `);

    // 4. Inject inner HTML
    snapshotContainer.innerHTML = `
        <div style="background-color: #ffffff; padding: 5px; width: 100%;">
            <h2 style="margin: 0 0 5px 0; font-size: 16px; text-transform: uppercase; color: #000000;">Withdrawal Request Summary Statement</h2>
            <p style="font-size: 11px; margin: 0 0 20px 0; color: #333333;"><b>Export Date:</b> ${dateStamp}</p>
            <table cellspacing="0" style="border-collapse: collapse; width: 100%; font-size: 11px; text-align: left; background-color: #ffffff; border: 1px solid #111111;">
                <thead>
                    <tr style="background-color: #f2f2f2; font-weight: bold; border-bottom: 2px solid #111111;">
                        <th style="padding: 12px 10px; border: 1px solid #111111; color: #000000;">Account Holder</th>
                        <th style="padding: 12px 10px; border: 1px solid #111111; color: #000000;">DMAT</th>
                        <th style="padding: 12px 10px; border: 1px solid #111111; text-align: right; color: #000000;">Amount</th>
                        <th style="padding: 12px 10px; border: 1px solid #111111; text-align: right; color: #000000;">Client</th>
                        <th style="padding: 12px 10px; border: 1px solid #111111; text-align: right; color: #000000;">Agent</th>
                        <th style="padding: 12px 10px; border: 1px solid #111111; text-align: right; color: #000000;">Manager</th>
                    </tr>
                </thead>
                <tbody>
                    ${matrixRowsHtml}
                    <tr style="background-color: #f9f9f9; font-weight: bold; color: #000000; border-top: 2px solid #111111;">
                        <td style="padding: 12px 10px; border: 1px solid #111111; text-transform: uppercase; color: #000000;">TOTALS</td>
                        <td style="border: 1px solid #111111;"></td>
                        <td style="padding: 12px 10px; border: 1px solid #111111; text-align: right; font-family: monospace; color: #000000;">${sumNet.toFixed(2)}</td>
                        <td style="padding: 12px 10px; border: 1px solid #111111; text-align: right; font-family: monospace; color: #000000;">${sumC.toFixed(2)}</td>
                        <td style="padding: 12px 10px; border: 1px solid #111111; text-align: right; font-family: monospace; color: #000000;">${sumA.toFixed(2)}</td>
                        <td style="padding: 12px 10px; border: 1px solid #111111; text-align: right; font-family: monospace; color: #000000;">${sumM.toFixed(2)}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;

    document.body.appendChild(snapshotContainer);
    await new Promise(resolve => setTimeout(resolve, 300));

    html2canvas(snapshotContainer, {
        scale: 4,
        backgroundColor: '#ffffff',
        windowWidth: 800,
        width: 800,
        useCORS: true,
        logging: false
    }).then(canvas => {
        let base64Image = canvas.toDataURL("image/png");
    let filename = `Withdrawal_${new Date().toISOString().slice(0,10)}.png`;
        let link = document.createElement('a');
        link.download = `Withdrawal_${new Date().toISOString().slice(0,10)}.png`;
        link.href = canvas.toDataURL("image/png");
        link.click();
        document.body.removeChild(snapshotContainer);


        // 2. Send to Server for WhatsApp
    $.ajax({
        url: 'json-api.php',
        type: 'POST',
        data: {
            action: 'send_whatsapp_image',
            image_data: base64Image,
            filename: filename,

        },
        success: function(response) {
            console.log("WhatsApp Result:", response);
        }
    });
    });
}