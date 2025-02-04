const express = require("express");
const router = express.Router();
const usersRouter = require("./users");
const testFun = require("../../utils/test");
router.use("/users", usersRouter);
module.exports = router;
