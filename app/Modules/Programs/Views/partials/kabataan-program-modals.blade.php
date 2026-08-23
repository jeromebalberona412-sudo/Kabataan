{{-- Shell modals populated dynamically by kabataan-programs.js --}}
<div id="educationModal" class="program-modal">
    <div class="modal-overlay"></div>
    <div class="modal-container education-modal-container" id="educationModalContainer" style="max-width: 900px;">
        <div class="modal-header">
            <h2>Education Programs</h2>
            <button type="button" class="modal-close" onclick="closeEducationModal()" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body education-modal-body">
            <div id="educationProgramsContainer">
                <p style="text-align:center;color:#64748b;padding:32px;">Loading programs…</p>
            </div>
        </div>
    </div>
</div>

<div id="sportsModal" class="program-modal">
    <div class="modal-overlay"></div>
    <div class="modal-container" style="max-width: 900px;">
        <div class="modal-header">
            <h2>Sports Development Programs</h2>
            <button type="button" class="modal-close" onclick="closeSportsModal()" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body sports-modal-body">
            <p class="sports-modal-loading" style="text-align:center;color:#64748b;padding:32px;">Loading sports programs…</p>
        </div>
    </div>
</div>

<div id="antiDrugsModal" class="program-modal">
    <div class="modal-overlay"></div>
    <div class="modal-container" style="max-width: 900px;">
        <div class="modal-header">
            <h2>Anti-Drugs Programs</h2>
            <button type="button" class="modal-close" onclick="closeAntiDrugsModal()" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body" style="padding: 24px; overflow-y: auto; max-height: calc(90vh - 80px);">
            <p style="text-align:center;color:#64748b;padding:32px;">Loading programs…</p>
        </div>
    </div>
</div>

<div id="agricultureModal" class="program-modal">
    <div class="modal-overlay"></div>
    <div class="modal-container" style="max-width: 900px;">
        <div class="modal-header">
            <h2>Agriculture Programs</h2>
            <button type="button" class="modal-close" onclick="closeAgricultureModal()" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body" style="padding: 24px; overflow-y: auto; max-height: calc(90vh - 80px);">
            <p style="text-align:center;color:#64748b;padding:32px;">Loading programs…</p>
        </div>
    </div>
</div>

<div id="disasterModal" class="program-modal">
    <div class="modal-overlay"></div>
    <div class="modal-container" style="max-width: 900px;">
        <div class="modal-header">
            <h2>Disaster Risk Reduction Programs</h2>
            <button type="button" class="modal-close" onclick="closeDisasterModal()" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body" style="padding: 24px; overflow-y: auto; max-height: calc(90vh - 80px);">
            <p style="text-align:center;color:#64748b;padding:32px;">Loading programs…</p>
        </div>
    </div>
</div>

<div id="genderModal" class="program-modal">
    <div class="modal-overlay"></div>
    <div class="modal-container" style="max-width: 900px;">
        <div class="modal-header">
            <h2>Gender and Development Programs</h2>
            <button type="button" class="modal-close" onclick="closeGenderModal()" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body" style="padding: 24px; overflow-y: auto; max-height: calc(90vh - 80px);">
            <p style="text-align:center;color:#64748b;padding:32px;">Loading programs…</p>
        </div>
    </div>
</div>

<div id="healthModal" class="program-modal">
    <div class="modal-overlay"></div>
    <div class="modal-container" style="max-width: 900px;">
        <div class="modal-header">
            <h2>Health Programs</h2>
            <button type="button" class="modal-close" onclick="closeHealthModal()" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body" style="padding: 24px; overflow-y: auto; max-height: calc(90vh - 80px);">
            <p style="text-align:center;color:#64748b;padding:32px;">Loading programs…</p>
        </div>
    </div>
</div>

<div id="othersModal" class="program-modal">
    <div class="modal-overlay"></div>
    <div class="modal-container" style="max-width: 900px;">
        <div class="modal-header">
            <h2>Other Programs</h2>
            <button type="button" class="modal-close" onclick="closeOthersModal()" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body" style="padding: 24px; overflow-y: auto; max-height: calc(90vh - 80px);">
            <p style="text-align:center;color:#64748b;padding:32px;">Loading programs…</p>
        </div>
    </div>
</div>
