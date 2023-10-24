import { Flex, Grid, GridCol, Paper, Skeleton, Stack } from '@mantine/core';
import React, { Suspense } from 'react';
import CourseWorkBreadcrumbs from '../heading/CourseWorkBreadcrumbs';
import StudentList from './StudentList';
import googleClassroom from '@/lib/config/googleClassroom';
import { AttachmentsView } from './AttachmentsView';
import Grader from './Grader';
import prisma from '@/lib/db';

type Props = {
  params: {
    courseId: string;
    courseWorkId: string;
  };
};

const getStudents = async (courseId: string) => {
  const classroom = await googleClassroom();
  const { data } = await classroom.courses.students.list({
    courseId,
  });
  return data.students;
};

const getSubmissions = async (courseId: string, courseWorkId: string) => {
  const classroom = await googleClassroom();
  const { data } = await classroom.courses.courseWork.studentSubmissions.list({
    courseId,
    courseWorkId,
    states: ['CREATED', 'TURNED_IN', 'RETURNED', 'RECLAIMED_BY_STUDENT'],
  });
  return data.studentSubmissions;
};

export default async function SubmissionsPage({
  params: { courseId, courseWorkId },
}: Props) {
  return (
    <>
      <CourseWorkBreadcrumbs courseId={courseId} courseWorkId={courseWorkId} />
      <Grid>
        <GridCol span={{ base: 12, md: 2 }}>
          <StudentListWrapper courseId={courseId} courseWorkId={courseWorkId} />
        </GridCol>
        <GridCol span={{ base: 12, md: 7 }}>
          <Paper withBorder h={'80vh'}>
            <AttachmentsView />
          </Paper>
        </GridCol>
        <GridCol span={{ base: 12, md: 3 }}>
          <GraderWrapper courseId={courseId} courseWorkId={courseWorkId} />
        </GridCol>
      </Grid>
    </>
  );
}

type WrapperProps = {
  courseId: string;
  courseWorkId: string;
};
async function StudentListWrapper({ courseId, courseWorkId }: WrapperProps) {
  const [students, submissions] = await Promise.all([
    getStudents(courseId!),
    getSubmissions(courseId!, courseWorkId!),
  ]);
  return (
    <Suspense fallback={<Skeleton h={{ base: 80, md: '85vh' }} />}>
      <StudentList students={students} submissions={submissions} />
    </Suspense>
  );
}

async function GraderWrapper({ courseId, courseWorkId }: WrapperProps) {
  const rubric = await prisma.rubric.findUnique({
    where: { courseId_courseWorkId: { courseId, courseWorkId } },
    include: { rubricItems: true },
  });

  return (
    <Suspense fallback={<Skeleton h={{ base: 80, md: '85vh' }} />}>
      <Grader criteria={rubric?.rubricItems} />
    </Suspense>
  );
}
