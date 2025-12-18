/**
 * QA Bridge
 * Facilitates communication between tools iframe and the main dashboard.
 */
console.log('QA Bridge loaded.');

// Helper to ensure API is ready
window.ensureApi = async function() {
    let attempts = 0;
    while (!window.parent || !window.parent.api) {
        if (attempts > 10) throw new Error("API not accessible from parent.");
        await new Promise(r => setTimeout(r, 200));
        attempts++;
    }
    return window.parent.api;
};
