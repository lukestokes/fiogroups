# FIO Groups

This is a prototype of an idea exploring group memberships using FIO. A DAC or DAO is, at it's core, simply a group with members who elect admins who then (via multisig) govern the group.

For more details on this idea, see: https://fioprotocol.atlassian.net/wiki/spaces/FC/pages/62423205/FIO+Groups

You can view the prototype running here: https://fiogroups.lukestokes.info/

You can view a slide deck presentation about FIO Groups here: http://bit.ly/fiogroups-deck

The basic flow:

* User logs in with Anchor Wallet.
* User creates a group which involves:
	* Registering the group FIO Domain
	* Registering their member name (FIO Address) on the group domain
	* Transfering 10 FIO to create a new FIO Group account
	* Adjusting the permissions on that account so they have active and owner control.
	* Transferring the domain to the group account.
* Others can apply to join the group which involves:
	* Requesting a member name (FIO Address) on the group domain
	* Sending tokens to the group treasury to pay for their member name
	* Creating an msig for the group admins to approve creating of their member name.
* Members can create a new election at any time if there is no currently active election.
* Members can register as admin candidates
* Members can vote on admin candidates in active elections
* Votes for an election can be counted to determine the new admins for the group
* Election results can be verified which creates a new msig for the previous admins to execute which gives over control of the group to the newly elected admins.
* Individuals can deactivate their accounts at any time.
* Individuals can reactivate a deactivated account at any time.
* Admins can disable an account at any time.
* Only admins can enable a disabled account.
