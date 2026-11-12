export class CameraSession {
    constructor(driver, onChange = () => {}) {
        this.driver = driver;
        this.onChange = onChange;
        this.running = false;
        this.starting = false;
        this.stopping = null;
        this.stopRequested = false;
    }

    get busy() {
        return this.starting || this.stopping !== null;
    }

    async start(...args) {
        if (this.running || this.busy) return false;
        this.starting = true;
        this.stopRequested = false;
        this.onChange();
        try {
            await this.driver.start(...args);
            this.running = true;
            if (this.stopRequested) {
                await this.stop();
                return false;
            }
            return true;
        } finally {
            this.starting = false;
            this.onChange();
        }
    }

    async stop() {
        // Permission to open the camera may still be pending in the browser.
        this.stopRequested = true;
        if (this.stopping) return this.stopping;
        if (!this.running) return;
        this.stopping = Promise.resolve().then(() => this.driver.stop());
        this.onChange();
        try {
            await this.stopping;
        } finally {
            this.running = false;
            this.stopping = null;
            this.onChange();
        }
    }
}
