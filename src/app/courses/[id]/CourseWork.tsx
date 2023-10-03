'use client';
import React from 'react';
import { classroom_v1 } from 'googleapis';
import { Accordion, Button, Divider, Flex } from '@mantine/core';

type Props = {
  courseWork: classroom_v1.Schema$CourseWork;
};

export default function CourseWork({ courseWork }: Props) {
  return (
    <Accordion.Item value={courseWork.title || ''}>
      <Accordion.Control>{courseWork.title}</Accordion.Control>
      <Accordion.Panel>
        {courseWork.description}
        <Divider mt='xl' mb='md' />
        <Flex justify='start'>
          <Button variant='filled' color='gray'>
            View Course
          </Button>
        </Flex>
      </Accordion.Panel>
    </Accordion.Item>
  );
}
