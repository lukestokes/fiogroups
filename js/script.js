
/* BEGIN ANCHOR METHODS */

// tries to restore session, called when document is loaded
function restoreSession() {
    link.restoreSession(identifier).then((result) => {
        session = result
        if (session) {
            didLogin()
        }
    })
}

// login and store session if sucessful
function login() {
    link.login(identifier).then((identity) => {
      //console.log(JSON.stringify(identity.proof));
      jQuery("#identity_proof").val(JSON.stringify(identity.proof));
      session = identity.session;
      didLogin();
      jQuery("#main_form").submit();
    });
}

// logout and remove session from storage
function logout() {
    jQuery("#actor").val("");
    session.remove();
}

// called when session was restored or created
function didLogin() {
    jQuery("#actor").val(session.auth.actor);
}

/* END ANCHOR METHODS */
