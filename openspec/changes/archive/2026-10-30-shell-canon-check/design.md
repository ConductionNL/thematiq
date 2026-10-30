# Design: shell-canon-check

The check reads boxes from the real page, not stylesheet values, because the canon is what the eye
sees: a rule that sets 42 px loses to a padding elsewhere. It does not change any size. Recording
the three gaps keeps the check green today and still makes every change of them visible, which is
what a ratchet is for; deciding them is a design question for Ruben.
