var express = require("express");

/* GET users listing. */
function listUsers(req, res) {
  const users = [
    { id: 1, name: "Afroj", email: "mohd.afroj@gmail.com", gender: "Male" },
    {
      id: 2,
      name: "Lokesh Singh",
      email: "lokesh.singh@gmail.com",
      gender: "Male",
    },
    { id: 3, name: "Anil", email: "anil@gmail.com", gender: "Male" },
    { id: 4, name: "Sahu", email: "sahu@gmail.com", gender: "Male" },
  ];

  res.json({ data: users, message: "Users are found!" });
}

/* create new user. */
function createUser(req, res, next) {
  const users = [
    { id: 1, name: "Afroj", email: "mohd.afroj@gmail.com", gender: "Male" },
    {
      id: 2,
      name: "Lokesh Singh",
      email: "lokesh.singh@gmail.com",
      gender: "Male",
    },
    { id: 3, name: "Anil", email: "anil@gmail.com", gender: "Male" },
    { id: 4, name: "Sahu", email: "sahu@gmail.com", gender: "Male" },
  ];
  res.json({ data: users, message: "Users are found!" });
}

/* create update user. */
function updateUser(req, res, next) {
  res.json({ data: [], message: "Users are found!" });
}

/* create delete user. */
function deleteUser(req, res, next) {
  res.json({ data: [], message: "Users are found!" });
}

module.exports = { createUser, updateUser, deleteUser, listUsers };
