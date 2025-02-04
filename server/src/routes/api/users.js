var express = require("express");
var router = express.Router();
const {
  createUser,
  listUsers,
  updateUser,
  deleteUser,
} = require("../../controllers/users");

/* Route for users listing */
router.get("/", listUsers);

/* Route for create user */
router.post("/", createUser);

/* Route for update user */
router.post("/", updateUser);

/* Route for delete user */
router.post("/", deleteUser);

module.exports = router;
